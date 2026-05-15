<?php

namespace App\Console\Commands;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use Illuminate\Console\Command;

/**
 * Снимает с публикации активные карточки строителей, если текст связанного поста
 * не проходит эвристики CatalogPublicationBuilderNonServiceSignals (найм, заказ без оффера и т.п.).
 */
class DisableActiveBuildersHiringTextCommand extends Command
{
    protected $signature = 'app:catalog:disable-active-builders-hiring-text
                            {--dry-run : Только список совпадений, без записи в БД}';

    protected $description = 'Снять с публикации активные карточки строителей по эвристикам не-услуги (CatalogPublicationBuilderNonServiceSignals)';

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
            if ($reasons === []) {
                continue;
            }

            $matched++;
            $preview = mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 90);
            $this->line(sprintf(
                'builder_id=%d api_channel_post_id=%d reasons=%s preview=%s',
                (int) $builder->id,
                (int) $builder->api_channel_post_id,
                implode(', ', $reasons),
                $preview
            ));

            if (! $dryRun) {
                $builder->status = ApiPostAiStatusEnum::InModeration;
                $builder->save();
                $updated++;
            }
        }

        if ($dryRun) {
            $this->info("Найдено совпадений: {$matched} (запись не выполнялась, --dry-run).");

            return self::SUCCESS;
        }

        $this->info("Найдено: {$matched}, переведено в модерацию (status=InModeration): {$updated}.");

        return self::SUCCESS;
    }
}
