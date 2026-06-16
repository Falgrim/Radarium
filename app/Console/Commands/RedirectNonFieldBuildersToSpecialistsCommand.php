<?php

namespace App\Console\Commands;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\BuilderNonFieldSpecialistRedirect;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use Illuminate\Console\Command;

/**
 * Переносит активные карточки строителей с текстом проектирования/визуализации/дизайна
 * в каталог проектировщиков (создаёт Specialist, удаляет Builder).
 */
class RedirectNonFieldBuildersToSpecialistsCommand extends Command
{
    protected $signature = 'app:catalog:redirect-non-field-builders-to-specialists
                            {--dry-run : Только список совпадений, без записи в БД}';

    protected $description = 'Перенести активные карточки строителей (проектирование/визуализация/дизайн) в каталог проектировщиков';

    public function handle(
        CatalogPublicationBuilderNonServiceSignals $signals,
        BuilderNonFieldSpecialistRedirect $redirect,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $matched = 0;
        $redirected = 0;
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

            $result = $redirect->redirectPost($post, 'catalog_bulk_redirect');
            if ($result['redirected']) {
                $redirected++;
                $this->info(sprintf(
                    '  → specialist_id=%d',
                    (int) ($result['import']['specialist_id'] ?? 0)
                ));
            } else {
                $failed++;
                $this->warn('  → не удалось: '.($result['import']['message'] ?? 'unknown'));
            }
        }

        if ($dryRun) {
            $this->info("Найдено совпадений: {$matched} (запись не выполнялась, --dry-run).");

            return self::SUCCESS;
        }

        $this->info("Найдено: {$matched}, перенесено в проектировщики: {$redirected}, ошибок: {$failed}.");

        return self::SUCCESS;
    }
}
