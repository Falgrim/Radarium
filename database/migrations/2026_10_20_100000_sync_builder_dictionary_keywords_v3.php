<?php

declare(strict_types=1);

use App\Data\BuilderSpecialityDictionaryData;
use App\Enum\ApiDataTypeEnum;
use App\Models\DictionarySpeciality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Идемпотентное обновление key_words / weak_key_words / negative_key_words (v3):
 * сильные синонимы (электрика, фасад, аутсорс, поставка и т.п.) без смены id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dictionary_specialities', 'weak_key_words')) {
            return;
        }

        $rows = BuilderSpecialityDictionaryData::rows();
        $existing = DictionarySpeciality::query()
            ->where('api_data_type_id', ApiDataTypeEnum::Builder)
            ->get();

        $byTitleParent = [];
        foreach ($existing as $item) {
            $parentTitle = null;
            if ($item->parent_id) {
                $parent = $existing->firstWhere('id', $item->parent_id);
                $parentTitle = $parent?->title;
            }
            $key = mb_strtolower(trim((string) $item->title)).'|'.mb_strtolower(trim((string) ($parentTitle ?? '')));
            $byTitleParent[$key] = $item;
        }

        $slugToTitle = [];
        foreach ($rows as $row) {
            $slugToTitle[$row['slug']] = $row['title'];
        }

        foreach ($rows as $row) {
            $parentTitle = '';
            if (! empty($row['parent_slug'])) {
                $parentTitle = (string) ($slugToTitle[$row['parent_slug']] ?? '');
            }
            $key = mb_strtolower(trim((string) $row['title'])).'|'.mb_strtolower(trim($parentTitle));
            $item = $byTitleParent[$key] ?? null;
            if ($item === null) {
                continue;
            }

            $item->key_words = array_values(array_filter(
                $row['key_words'] ?? [],
                static fn ($k): bool => is_string($k) && $k !== ''
            ));
            $item->weak_key_words = array_values(array_filter(
                $row['weak_key_words'] ?? [],
                static fn ($k): bool => is_string($k) && $k !== ''
            ));
            $item->negative_key_words = array_values(array_filter(
                $row['negative_key_words'] ?? [],
                static fn ($k): bool => is_string($k) && $k !== ''
            ));
            $item->save();
        }
    }

    public function down(): void
    {
        // Ключи не откатываем — обратимость через предыдущий JSON/бэкап.
    }
};
