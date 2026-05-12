<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use Illuminate\Console\Command;

/**
 * Разовая/периодическая чистка: активные builder-карточки, у которых исходный пост по тексту похож на найм персонала.
 */
final class DisableActiveBuildersHiringTextCommand extends Command
{
    protected $signature = 'app:catalog:disable-active-builders-hiring-text
                            {--dry-run : Только список совпадений, без записи в БД}';

    protected $description = 'Отключить активные карточки строителей, если текст связанного поста содержит маркеры найма (см. CatalogPublicationBuilderNonServiceSignals)';

    public function handle(CatalogPublicationBuilderNonServiceSignals $signals): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $matched = 0;
        $updated = 0;

        $query = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0)
            ->with('post');

        foreach ($query->cursor() as $builder) {
            $post = $builder->post;
            $text = trim((string) ($post?->post ?? ''));
            if ($text === '') {
                continue;
            }

            $reasons = $signals->reasons($text);
            if (! in_array(CatalogPublicationBuilderNonServiceSignals::REASON_HIRING, $reasons, true)) {
                continue;
            }

            $matched++;
            $this->line(sprintf(
                'builder_id=%d api_channel_post_id=%d preview=%s',
                (int) $builder->id,
                (int) $builder->api_channel_post_id,
                mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 90)
            ));

            if (! $dryRun) {
                $builder->status = ApiPostAiStatusEnum::Disabled;
                $builder->save();
                $updated++;
            }
        }

        if ($dryRun) {
            $this->info("Найдено совпадений: {$matched} (запись не выполнялась, --dry-run).");

            return self::SUCCESS;
        }

        $this->info("Найдено: {$matched}, отключено (status=Disabled): {$updated}.");

        return self::SUCCESS;
    }
}
