<?php

use App\Data\BuilderSpecialityDictionaryData;
use App\Enum\ApiDataTypeEnum;
use App\Models\BuilderSpeciality;
use App\Models\DictionarySpeciality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Пересид справочника builder по DOC/builder_specialities_updated.xlsx (лист «Специализации»).
     * Очищает связи builder_specialities, заново вставляет dictionary_specialities.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $builderType = ApiDataTypeEnum::Builder->value;

            $oldIds = DictionarySpeciality::query()
                ->where('api_data_type_id', $builderType)
                ->pluck('id');

            if ($oldIds->isNotEmpty()) {
                BuilderSpeciality::query()
                    ->whereIn('dictionary_speciality_id', $oldIds)
                    ->delete();
            }

            DictionarySpeciality::query()
                ->where('api_data_type_id', $builderType)
                ->update(['parent_id' => null]);

            DictionarySpeciality::query()
                ->where('api_data_type_id', $builderType)
                ->delete();

            $slugToId = [];
            $now = now();

            foreach (BuilderSpecialityDictionaryData::rows() as $row) {
                $cleanKeywords = array_values(array_unique(array_filter(array_map(
                    static fn (string $kw): string => preg_replace('#([^0-9a-zа-яё\-,\.\s\(\)/+])+#iu', '', $kw),
                    $row['key_words']
                ))));

                $created = DictionarySpeciality::query()->create([
                    'parent_id' => null,
                    'group_title' => $row['group_title'],
                    'title' => $row['title'],
                    'short_name' => $row['short_name'],
                    'key_words' => $cleanKeywords,
                    'api_data_type_id' => $builderType,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $slugToId[$row['slug']] = $created->id;
            }

            foreach (BuilderSpecialityDictionaryData::rows() as $row) {
                if (empty($row['parent_slug'])) {
                    continue;
                }

                $childId = $slugToId[$row['slug']] ?? null;
                $parentId = $slugToId[$row['parent_slug']] ?? null;
                if ($childId === null || $parentId === null) {
                    continue;
                }

                DictionarySpeciality::query()->whereKey($childId)->update(['parent_id' => $parentId]);
            }
        });
    }

    public function down(): void
    {
        // Как 2026_04_21_100001: откат без бэкапа не восстанавливает прежний набор.
    }
};
