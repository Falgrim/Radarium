<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BuilderSpeciality;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентная синхронизация связей builder ↔ dictionary_specialities с учётом source (auto|manual).
 *
 * Правила:
 *  - syncAuto(): перезаписываются только auto-строки. Manual-строки никогда не трогаются.
 *  - setManualIds(): полная перестановка набора, выбранного модератором в админке.
 *    Всё, что модератор оставил — становится manual (auto-строки апгрейдятся до manual).
 *    Всё, что убрал — физически удаляется.
 */
final class BuilderSpecialitySyncService
{
    /**
     * Привести набор auto-специализаций билдера к $desiredIds.
     * Manual-связи сохраняются как есть.
     *
     * @param  list<int>  $desiredIds
     * @return array{added: list<int>, removed: list<int>, kept_manual: list<int>}
     */
    public function syncAuto(int $builderId, array $desiredIds): array
    {
        $desiredIds = $this->normalizeIds($desiredIds);

        return DB::transaction(function () use ($builderId, $desiredIds): array {
            $existing = BuilderSpeciality::query()
                ->where('builder_id', $builderId)
                ->get(['id', 'dictionary_speciality_id', 'source']);

            $manualIds = $existing
                ->where('source', BuilderSpeciality::SOURCE_MANUAL)
                ->pluck('dictionary_speciality_id')
                ->map('intval')
                ->values()
                ->all();

            $currentAutoById = $existing
                ->where('source', BuilderSpeciality::SOURCE_AUTO)
                ->keyBy(static fn ($row) => (int) $row->dictionary_speciality_id);

            $currentAutoIds = array_map('intval', array_keys($currentAutoById->all()));

            $toRemove = array_values(array_diff($currentAutoIds, $desiredIds));
            $toAdd = array_values(array_diff($desiredIds, $currentAutoIds, $manualIds));

            if ($toRemove !== []) {
                BuilderSpeciality::query()
                    ->where('builder_id', $builderId)
                    ->where('source', BuilderSpeciality::SOURCE_AUTO)
                    ->whereIn('dictionary_speciality_id', $toRemove)
                    ->delete();
            }

            foreach ($toAdd as $specialityId) {
                BuilderSpeciality::query()->firstOrCreate(
                    [
                        'builder_id' => $builderId,
                        'dictionary_speciality_id' => $specialityId,
                    ],
                    [
                        'source' => BuilderSpeciality::SOURCE_AUTO,
                    ]
                );
            }

            return [
                'added' => $toAdd,
                'removed' => $toRemove,
                'kept_manual' => $manualIds,
            ];
        });
    }

    /**
     * Полный набор специализаций билдера, заданный вручную (модератором).
     * Всё в $desiredIds становится manual (auto-записи апгрейдятся).
     * Записи, которых нет в $desiredIds, удаляются (любого source).
     *
     * @param  list<int>  $desiredIds
     * @return array{added: list<int>, removed: list<int>, upgraded: list<int>}
     */
    public function setManualIds(int $builderId, array $desiredIds): array
    {
        $desiredIds = $this->normalizeIds($desiredIds);

        return DB::transaction(function () use ($builderId, $desiredIds): array {
            $existing = BuilderSpeciality::query()
                ->where('builder_id', $builderId)
                ->get(['id', 'dictionary_speciality_id', 'source']);

            $existingById = $existing->keyBy(static fn ($row) => (int) $row->dictionary_speciality_id);
            $existingIds = array_map('intval', array_keys($existingById->all()));

            $toRemove = array_values(array_diff($existingIds, $desiredIds));
            $added = [];
            $upgraded = [];

            if ($toRemove !== []) {
                BuilderSpeciality::query()
                    ->where('builder_id', $builderId)
                    ->whereIn('dictionary_speciality_id', $toRemove)
                    ->delete();
            }

            foreach ($desiredIds as $specialityId) {
                $row = $existingById->get($specialityId);
                if ($row === null) {
                    BuilderSpeciality::query()->create([
                        'builder_id' => $builderId,
                        'dictionary_speciality_id' => $specialityId,
                        'source' => BuilderSpeciality::SOURCE_MANUAL,
                    ]);
                    $added[] = $specialityId;

                    continue;
                }

                if ($row->source !== BuilderSpeciality::SOURCE_MANUAL) {
                    BuilderSpeciality::query()
                        ->whereKey($row->id)
                        ->update(['source' => BuilderSpeciality::SOURCE_MANUAL]);
                    $upgraded[] = $specialityId;
                }
            }

            return [
                'added' => $added,
                'removed' => $toRemove,
                'upgraded' => $upgraded,
            ];
        });
    }

    /**
     * @return list<int>
     */
    public function getAllIds(int $builderId): array
    {
        return BuilderSpeciality::query()
            ->where('builder_id', $builderId)
            ->pluck('dictionary_speciality_id')
            ->map('intval')
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function getManualIds(int $builderId): array
    {
        return BuilderSpeciality::query()
            ->where('builder_id', $builderId)
            ->where('source', BuilderSpeciality::SOURCE_MANUAL)
            ->pluck('dictionary_speciality_id')
            ->map('intval')
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $v) {
            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                $int = (int) $v;
                if ($int > 0) {
                    $clean[$int] = true;
                }
            }
        }

        return array_map('intval', array_keys($clean));
    }
}
