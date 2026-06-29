<?php

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
 * в каталог проектировщиков (создаёт Specialist, удаляет Builder).
 */
class RedirectNonFieldBuildersToSpecialistsCommand extends Command
{
    protected $signature = 'app:catalog:redirect-non-field-builders-to-specialists
                            {--dry-run : Только список совпадений, без записи в БД}
                            {--requeue-if-no-ai : При недоступности ИИ оставить builder и пост InQueue (без эвристического fallback)}';

    protected $description = 'Перенести активные карточки строителей (проектирование/визуализация/дизайн) в каталог проектировщиков';

    public function handle(
        CatalogPublicationBuilderNonServiceSignals $signals,
        BuilderNonFieldSpecialistRedirect $redirect,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $requeueIfNoAi = (bool) $this->option('requeue-if-no-ai');
        $matched = 0;
        $redirected = 0;
        $heuristic = 0;
        $requeued = 0;
        $failed = 0;

        $query = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0)
            ->with(['post.channel.apiAi', 'post.apiPostUser']);

        foreach ($query->cursor() as $builder) {
            $post = $builder->post;
            $text = trim((string) ($post?->post ?? ''));
            if ($text === '' || ! $signals->shouldRedirectToSpecialist($text)) {
                continue;
            }

            $matched++;
            $preview = mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 90);
            $this->line(sprintf(
                'builder_id=%d api_channel_post_id=%d preview=%s',
                (int) $builder->id,
                (int) $builder->api_channel_post_id,
                $preview
            ));

            if ($dryRun) {
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

            if ($result['redirected'] && $import !== null) {
                $redirected++;
                $note = ($import['message'] ?? '') !== '' ? ' ('.$import['message'].')' : '';
                if (str_contains((string) ($import['message'] ?? ''), 'эвристическ')) {
                    $heuristic++;
                }
                $this->info(sprintf(
                    '  → specialist_id=%d%s',
                    (int) ($import['specialist_id'] ?? 0),
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
            $this->info("Найдено совпадений: {$matched} (запись не выполнялась, --dry-run).");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Найдено: %d, перенесено: %d (из них без ИИ: %d), отложено (InQueue): %d, ошибок: %d.',
            $matched,
            $redirected,
            $heuristic,
            $requeued,
            $failed
        ));

        return self::SUCCESS;
    }
}
