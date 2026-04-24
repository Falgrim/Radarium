<?php

namespace App\Support;

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
