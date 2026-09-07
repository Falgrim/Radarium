<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\RussianRegionNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Фильтр региона в публичном каталоге (строители и проектировщики).
 *
 * Режим задаётся config('catalog_region_filter.mode'):
 * - aggregated — три корзины (Москва и МО / СПб и ЛО / все кроме МСК и СПБ);
 * - legacy — прежний список канонических субъектов и городов ≥100 тыс. + опция «none».
 *
 * Откат: CATALOG_REGION_FILTER_MODE=legacy в .env и config:clear / gitupdate.sh.
 */
final class CatalogRegionFilter
{
    public const MODE_AGGREGATED = 'aggregated';

    public const MODE_LEGACY = 'legacy';

    public static function mode(): string
    {
        $mode = (string) config('catalog_region_filter.mode', self::MODE_AGGREGATED);

        return $mode === self::MODE_LEGACY ? self::MODE_LEGACY : self::MODE_AGGREGATED;
    }

    public static function isAggregatedMode(): bool
    {
        return self::mode() === self::MODE_AGGREGATED;
    }

    public static function isLegacyMode(): bool
    {
        return self::mode() === self::MODE_LEGACY;
    }

    /**
     * @return array{0: array<string, string>, 1: bool} [regionsList, showNoneOption]
     */
    public static function dropdownForAggregated(): array
    {
        return [CatalogRegionBuckets::aggregatedSelectOptions(), false];
    }

    /**
     * Legacy: канонический список из distinct region + флаг «без географии».
     *
     * @param  list<mixed>  $rawDistinctFromDatabase
     * @return array{0: array<string, string>, 1: bool}
     */
    public static function dropdownForLegacy(
        RussianRegionNormalizer $normalizer,
        array $rawDistinctFromDatabase,
        bool $hasNullRegion
    ): array {
        $regionsList = CatalogRegionOptions::catalogCanonicalChoicesFromDistinctRaw(
            $normalizer,
            $rawDistinctFromDatabase
        );

        return [$regionsList, $hasNullRegion];
    }

    /**
     * @param  array<string, string>  $regionsList
     * @return list<string>
     */
    public static function allowedRequestValues(array $regionsList, bool $showNoneOption): array
    {
        $allowed = array_merge([''], array_keys($regionsList));
        if ($showNoneOption) {
            $allowed[] = 'none';
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Применить выбранный пункт фильтра к запросу карточек (builders / specialists).
     * Ожидается relation post.channel у модели карточки.
     */
    public static function applyToCardQuery(Builder $query, ?string $regionFilter): void
    {
        if ($regionFilter === null || $regionFilter === '') {
            return;
        }

        if (self::isAggregatedMode()) {
            self::applyAggregated($query, $regionFilter);

            return;
        }

        self::applyLegacy($query, $regionFilter);
    }

    private static function applyLegacy(Builder $query, string $regionFilter): void
    {
        if ($regionFilter === 'none') {
            $query->where(function (Builder $inner): void {
                CatalogRegionOptions::applyRegionMissingConstraint($inner);
            });

            return;
        }

        $query->where('region', $regionFilter);
    }

    private static function applyAggregated(Builder $query, string $regionFilter): void
    {
        match ($regionFilter) {
            CatalogRegionBuckets::FILTER_MOSCOW => $query->where(
                fn (Builder $q) => self::matchAgglomeration($q, CatalogRegionBuckets::moscowCanonicalRegions(), CatalogRegionBuckets::moscowChannelTitleNeedles())
            ),
            CatalogRegionBuckets::FILTER_SPB => $query->where(
                fn (Builder $q) => self::matchAgglomeration($q, CatalogRegionBuckets::spbCanonicalRegions(), CatalogRegionBuckets::spbChannelTitleNeedles())
            ),
            CatalogRegionBuckets::FILTER_EXCEPT_MSK_SPB => $query
                ->where(fn (Builder $q) => self::matchNotAgglomeration(
                    $q,
                    CatalogRegionBuckets::moscowCanonicalRegions(),
                    CatalogRegionBuckets::moscowChannelTitleNeedles()
                ))
                ->where(fn (Builder $q) => self::matchNotAgglomeration(
                    $q,
                    CatalogRegionBuckets::spbCanonicalRegions(),
                    CatalogRegionBuckets::spbChannelTitleNeedles()
                )),
            default => null,
        };
    }

    /**
     * Карточка в агломерации: region карточки ИЛИ region/название источника.
     *
     * @param  list<string>  $canonicalRegions
     * @param  list<string>  $titleNeedlesLower
     */
    public static function matchAgglomeration(Builder $query, array $canonicalRegions, array $titleNeedlesLower): void
    {
        $query->whereIn('region', $canonicalRegions)
            ->orWhereHas('post.channel', function (Builder $channel) use ($canonicalRegions, $titleNeedlesLower): void {
                self::matchChannelAgglomeration($channel, $canonicalRegions, $titleNeedlesLower);
            });
    }

    /**
     * Карточка вне агломерации (включая пустой region), если источник тоже не относится к ней.
     *
     * @param  list<string>  $canonicalRegions
     * @param  list<string>  $titleNeedlesLower
     */
    public static function matchNotAgglomeration(Builder $query, array $canonicalRegions, array $titleNeedlesLower): void
    {
        $query->where(function (Builder $regionPart) use ($canonicalRegions): void {
            $regionPart->whereNull('region')
                ->orWhere('region', '')
                ->orWhereNotIn('region', $canonicalRegions);
        })->whereDoesntHave('post.channel', function (Builder $channel) use ($canonicalRegions, $titleNeedlesLower): void {
            self::matchChannelAgglomeration($channel, $canonicalRegions, $titleNeedlesLower);
        });
    }

    /**
     * @param  list<string>  $canonicalRegions
     * @param  list<string>  $titleNeedlesLower
     */
    private static function matchChannelAgglomeration(
        Builder $channel,
        array $canonicalRegions,
        array $titleNeedlesLower
    ): void {
        $channel->where(function (Builder $c) use ($canonicalRegions, $titleNeedlesLower): void {
            $c->whereIn('region', $canonicalRegions);
            foreach ($titleNeedlesLower as $needle) {
                $c->orWhereRaw('LOWER(title) LIKE ?', ['%'.$needle.'%']);
            }
        });
    }
}
