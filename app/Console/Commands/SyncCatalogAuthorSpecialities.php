<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Models\Specialist;
use App\Services\AuthorCatalogSpecialitiesSync;
use Illuminate\Console\Command;

class SyncCatalogAuthorSpecialities extends Command
{
    protected $signature = 'app:catalog:sync_author_specialities
                            {--dry-run : Показать изменения без записи в БД}
                            {--only=both : Область: both|specialists|builders}
                            {--max=3 : Максимум специализаций на автора (фактически 1…N, если совпадений меньше)}';

    protected $description = 'Пересчитать специализации авторов публичного каталога (специалисты и/или строители): не более N по совокупности их сообщений с завершённой ИИ-обработкой';

    public function handle(AuthorCatalogSpecialitiesSync $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = strtolower(trim((string) $this->option('only')));
        $max = max(1, (int) $this->option('max'));

        if (! in_array($only, ['both', 'specialists', 'builders'], true)) {
            $this->error('Параметр --only должен быть: both, specialists или builders');

            return self::INVALID;
        }

        $changedAuthors = 0;
        $processedAuthors = 0;

        $userIds = $this->distinctCatalogAuthorUserIds($only);
        $this->info('Найдено авторов: '.count($userIds));

        foreach ($userIds as $userId) {
            ++$processedAuthors;
            $authorChanged = false;

            if ($only === 'both' || $only === 'specialists') {
                $r = $sync->syncSpecialistsForUser($userId, $max, $dryRun);
                if ($r['changed']) {
                    $authorChanged = true;
                }
            }

            if ($only === 'both' || $only === 'builders') {
                $r = $sync->syncBuildersForUser($userId, $max, $dryRun);
                if ($r['changed']) {
                    $authorChanged = true;
                }
            }

            if ($authorChanged) {
                ++$changedAuthors;
                if ($dryRun) {
                    $this->line("api_post_user_id={$userId}: будут обновлены специализации (max={$max})");
                }
            }
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info("Обработано авторов: {$processedAuthors}, с изменениями: {$changedAuthors}{$suffix}");

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function distinctCatalogAuthorUserIds(string $only): array
    {
        $ids = [];

        if ($only === 'both' || $only === 'specialists') {
            $ids = array_merge($ids, Specialist::query()
                ->where('status', ApiPostAiStatusEnum::Active)
                ->whereHas('post', function ($q): void {
                    $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
                })
                ->distinct()
                ->pluck('api_post_user_id')
                ->map('intval')
                ->all());
        }

        if ($only === 'both' || $only === 'builders') {
            $ids = array_merge($ids, Builder::query()
                ->where('status', ApiPostAiStatusEnum::Active)
                ->whereHas('post', function ($q): void {
                    $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
                })
                ->distinct()
                ->pluck('api_post_user_id')
                ->map('intval')
                ->all());
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        sort($ids);

        return $ids;
    }
}
