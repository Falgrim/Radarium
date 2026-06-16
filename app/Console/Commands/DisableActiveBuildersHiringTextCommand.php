<?php

namespace App\Console\Commands;

use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\BuilderNonFieldSpecialistRedirect;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use Illuminate\Console\Command;

/**
 * Снимает с публикации активные карточки строителей, если текст связанного поста
 * не проходит эвристики CatalogPublicationBuilderNonServiceSignals (найм, заказ без оффера и т.п.).
 * Посты проектирования/визуализации/дизайна переносятся в каталог проектировщиков.
 */
class DisableActiveBuildersHiringTextCommand extends Command
{
    protected $signature = 'app:catalog:disable-active-builders-hiring-text
                            {--dry-run : Только список совпадений, без записи в БД}';

    protected $description = 'Снять с публикации активные карточки строителей по эвристикам не-услуги (найм, проектирование → проектировщики и т.п.)';

    public function handle(
        CatalogPublicationBuilderNonServiceSignals $signals,
        BuilderNonFieldSpecialistRedirect $redirect,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $matched = 0;
        $updated = 0;
        $redirected = 0;

        $query = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0)
            ->with(['post.channel.apiAi', 'post.apiPostUser']);

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

            if ($dryRun) {
                continue;
            }

            if (in_array(CatalogPublicationBuilderNonServiceSignals::REASON_NON_FIELD_CONSTRUCTION, $reasons, true)) {
                if ($post !== null) {
                    $redirectResult = $redirect->redirectPost($post, 'disable_hiring_text_command');
                    if ($redirectResult['redirected']) {
                        $redirected++;

                        continue;
                    }
                }
            }

            $builder->status = ApiPostAiStatusEnum::InModeration;
            $builder->save();
            $updated++;
        }

        if ($dryRun) {
            $this->info("Найдено совпадений: {$matched} (запись не выполнялась, --dry-run).");

            return self::SUCCESS;
        }

        $this->info("Найдено: {$matched}, перенесено в проектировщики: {$redirected}, переведено в модерацию: {$updated}.");

        return self::SUCCESS;
    }
}
