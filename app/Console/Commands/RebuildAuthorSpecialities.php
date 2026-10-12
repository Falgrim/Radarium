<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\AuthorSpecialityProfiler;
use Illuminate\Console\Command;

class RebuildAuthorSpecialities extends Command
{
    protected $signature = 'app:builders:rebuild_author_specialities
                            {--dry-run : Показать diff без записи}
                            {--chunk=100 : Сколько авторов обрабатывать с отчётом}
                            {--compare-csv= : Путь CSV сравнения старых pivot vs новый профиль}
                            {--user= : Один api_post_user_id}';

    protected $description = 'Пересчитать профили специализаций авторов builders (scores + pivot, с учётом shadow)';

    public function handle(AuthorSpecialityProfiler $profiler): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $csvPath = $this->option('compare-csv');
        $oneUser = $this->option('user');
        $oneUserId = $oneUser !== null && $oneUser !== '' ? (int) $oneUser : null;

        $userIds = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->when($oneUserId !== null, fn ($q) => $q->where('api_post_user_id', $oneUserId))
            ->distinct()
            ->pluck('api_post_user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->sort()
            ->values()
            ->all();

        $this->info('Авторов: '.count($userIds));

        $changed = 0;
        $csvHandle = null;
        if (is_string($csvPath) && $csvPath !== '') {
            $csvHandle = fopen($csvPath, 'wb');
            if ($csvHandle === false) {
                $this->error("Не удалось открыть {$csvPath}");

                return self::FAILURE;
            }
            fputcsv($csvHandle, [
                'api_post_user_id',
                'old_speciality_ids',
                'new_speciality_ids',
                'changed',
                'shadow',
                'top_scores',
            ], ';');
        }

        foreach ($userIds as $userId) {
            $oldIds = $this->currentPivotIds($userId);
            $r = $profiler->profileBuildersForUser(
                $userId,
                (int) config('author_speciality_profile.max_specialities', 3),
                $dryRun,
                true
            );
            $newIds = $r['top_speciality_ids'];
            $isChanged = $oldIds !== (static function () use ($newIds): array {
                $x = $newIds;
                sort($x);

                return $x;
            })();

            if ($isChanged) {
                ++$changed;
            }

            if ($csvHandle) {
                $topScores = collect($r['scores'])
                    ->take(5)
                    ->map(static fn (array $s): string => $s['dictionary_speciality_id'].':'.$s['score'])
                    ->implode(',');
                fputcsv($csvHandle, [
                    $userId,
                    implode(',', $oldIds),
                    implode(',', $newIds),
                    $isChanged ? '1' : '0',
                    ($r['shadow'] ?? false) ? '1' : '0',
                    $topScores,
                ], ';');
            }

            if ($dryRun && $isChanged) {
                $this->line("user={$userId}: [".implode(',', $oldIds).'] → ['.implode(',', $newIds).']');
            }
        }

        if ($csvHandle) {
            fclose($csvHandle);
            $this->info("CSV: {$csvPath}");
        }

        $this->info(
            "Авторов с отличием от текущего pivot: {$changed}"
            .($dryRun ? ' (dry-run)' : '')
            .(config('author_speciality_profile.shadow') ? ' [shadow]' : '')
        );

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function currentPivotIds(int $userId): array
    {
        $builderIds = Builder::query()
            ->where('api_post_user_id', $userId)
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->pluck('id');

        if ($builderIds->isEmpty()) {
            return [];
        }

        $ids = \App\Models\BuilderSpeciality::query()
            ->whereIn('builder_id', $builderIds)
            ->pluck('dictionary_speciality_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids;
    }
}
