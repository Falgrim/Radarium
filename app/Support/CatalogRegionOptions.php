<?php

namespace App\Support;

use App\Services\RussianRegionNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Нормализация значений region для каталога: отсечение мусорных строк из ИИ/JSON в выпадающем списке
 * и единая трактовка «география не указана» для фильтра «без указания географии».
 */
final class CatalogRegionOptions
{
    /**
     * @return list<string> нижний регистр, для сравнения с LOWER(TRIM(region))
     */
    public static function junkRegionTokensLower(): array
    {
        return ['null', 'undefined', 'nil', 'n/a', 'na', '-', '—'];
    }

    public static function isJunkRegionString(string $value): bool
    {
        $t = trim($value);
        if ($t === '') {
            return true;
        }

        return in_array(mb_strtolower($t, 'UTF-8'), self::junkRegionTokensLower(), true);
    }

    /**
     * @param  list<mixed>  $rawNames
     * @return array<string, string>
     */
    public static function choicesFromRawNames(array $rawNames): array
    {
        $out = [];
        foreach ($rawNames as $name) {
            if (! is_string($name)) {
                continue;
            }
            $t = trim($name);
            if (self::isJunkRegionString($t)) {
                continue;
            }
            $out[$t] = $t;
        }

        return $out;
    }

    /**
     * Опции выпадающего списка: только канон из справочника регионов, после {@see RussianRegionNormalizer::normalize()}.
     *
     * @param  array<string, string>|null  $allowedRegionsWhitelist  Если null — используется config('regions').
     * @param  list<mixed>  $rawDistinctFromDatabase
     * @return array<string, string>
     */
    public static function catalogCanonicalChoicesFromDistinctRaw(
        RussianRegionNormalizer $normalizer,
        array $rawDistinctFromDatabase,
        ?array $allowedRegionsWhitelist = null
    ): array {
        $allowedRegions = $allowedRegionsWhitelist ?? config('regions', []);
        if (! is_array($allowedRegions) || $allowedRegions === []) {
            return self::choicesFromRawNames($rawDistinctFromDatabase);
        }

        $out = [];
        foreach ($rawDistinctFromDatabase as $name) {
            if (! is_string($name)) {
                continue;
            }
            $t = trim($name);
            if (self::isJunkRegionString($t)) {
                continue;
            }
            $canonical = $normalizer->normalize($t);
            if ($canonical === null || ! array_key_exists($canonical, $allowedRegions)) {
                continue;
            }
            $out[$canonical] = $canonical;
        }
        ksort($out, SORT_STRING);

        return $out;
    }

    /**
     * Условие: в БД нет осмысленного региона (NULL, пусто или мусорная строка).
     */
    public static function applyRegionMissingConstraint(Builder $query): void
    {
        $query->whereNull('region')
            ->orWhere('region', '');
        foreach (self::junkRegionTokensLower() as $token) {
            $query->orWhereRaw('LOWER(TRIM(region)) = ?', [$token]);
        }
    }
}
