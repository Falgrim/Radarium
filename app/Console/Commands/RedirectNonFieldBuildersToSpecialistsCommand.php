<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\BuilderNonFieldSpecialistRedirect;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use App\Services\SpecialistPostImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Переносит активные карточки строителей с текстом проектирования/визуализации/дизайна
 * в каталог проектировщиков. Если автор уже есть в проектировщиках — удаляет все его Builder.
 */
class RedirectNonFieldBuildersToSpecialistsCommand extends Command
{
    protected $signature = 'app:catalog:redirect-non-field-builders-to-specialists
                            {--dry-run : Только список совпадений, без записи в БД}
                            {--requeue-if-no-ai : При недоступности ИИ оставить builder и пост InQueue (без эвристического fallback)}';

    protected $description = 'Перенести активные карточки строителей (проектирование/визуализация/дизайн) в каталог проектировщиков; при наличии Specialist — убрать Builder';

    public function handle(
        CatalogPublicationBuilderNonServiceSignals $signals,
        BuilderNonFieldSpecialistRedirect $redirect,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $requeueIfNoAi = (bool) $this->option('requeue-if-no-ai');
        $matched = 0;
        $redirected = 0;
        $alreadySpecialist = 0;
        $heuristic = 0;
        $requeued = 0;
        $failed = 0;
        $buildersRemoved = 0;
        /** @var array<int, true> $processedAuthors авторы, уже обработанные (чтобы не гонять повторно после purge) */
        $processedAuthors = [];

        $query = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0)
            ->with(['post.channel.apiAi', 'post.apiPostUser']);

        foreach ($query->cursor() as $builder) {
            $userId = (int) ($builder->api_post_user_id ?? 0);
            if ($userId > 0 && isset($processedAuthors[$userId])) {
                continue;
            }

            $post = $builder->post;
            $text = trim((string) ($post?->post ?? ''));
            if ($text === '' || ! $signals->shouldRedirectToSpecialist($text)) {
                continue;
            }

            $matched++;
            $preview = mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 90);
            $already = $userId > 0 && $redirect->authorHasCatalogSpecialist($userId);
            $this->line(sprintf(
                'builder_id=%d user=%d already_specialist=%s api_channel_post_id=%d preview=%s',
                (int) $builder->id,
                $userId,
                $already ? 'yes' : 'no',
                (int) $builder->api_channel_post_id,
                $preview
            ));

            if ($dryRun) {
                if ($userId > 0) {
                    $processedAuthors[$userId] = true;
                }

                continue;
            }

            if ($post === null) {
                $failed++;

                continue;
            }

            try {
                $result = $redirect->redirectPost($post, 'catalog_bulk_redirect', [
                    'requeue_if_no_ai' => $requeueIfNoAi,
                ]);
            } catch (Throwable $e) {
                $failed++;
                $this->warn('  → ошибка: '.$e->getMessage());

                continue;
            }

            $import = $result['import'];
            $removed = (int) ($result['builders_removed'] ?? 0);
            $buildersRemoved += $removed;

            if ($userId > 0) {
                $processedAuthors[$userId] = true;
            }

            if ($result['redirected'] && $import !== null) {
                $redirected++;
                $status = (string) ($import['status'] ?? '');
                if ($status === BuilderNonFieldSpecialistRedirect::STATUS_ALREADY_SPECIALIST) {
                    $alreadySpecialist++;
                    $this->info(sprintf(
                        '  → уже проектировщик, удалено builder-карточек: %d (specialist_id=%d)',
                        $removed,
                        (int) ($import['specialist_id'] ?? 0)
                    ));

                    continue;
                }

                $note = ($import['message'] ?? '') !== '' ? ' ('.$import['message'].')' : '';
                if (str_contains((string) ($import['message'] ?? ''), 'эвристическ')) {
                    $heuristic++;
                }
                $this->info(sprintf(
                    '  → specialist_id=%d, удалено builder: %d%s',
                    (int) ($import['specialist_id'] ?? 0),
                    $removed,
                    $note
                ));

                continue;
            }

            if ($import !== null && ($import['status'] ?? '') === SpecialistPostImporter::STATUS_AI_UNAVAILABLE) {
                $requeued++;
                $this->warn('  → ИИ недоступен, пост оставлен InQueue, builder не удалён');

                continue;
            }

            $failed++;
            $this->warn('  → не удалось: '.($import['message'] ?? 'unknown'));
        }

        if ($dryRun) {
            $this->info("Найдено совпадений (уник. авторов/карточек в проходе): {$matched} (запись не выполнялась, --dry-run).");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Найдено: %d, обработано успешно: %d (уже были проектировщиками: %d, без ИИ: %d), удалено builder-карточек: %d, отложено (InQueue): %d, ошибок: %d.',
            $matched,
            $redirected,
            $alreadySpecialist,
            $heuristic,
            $buildersRemoved,
            $requeued,
            $failed
        ));

        return self::SUCCESS;
    }
}
