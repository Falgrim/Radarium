<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Builder;
use App\Services\RussianRegionNormalizer;

/**
 * Подпись региона в публичном каталоге строителей:
 * агломерации → «Москва и МО» / «Санкт-Петербург и ЛО»,
 * остальные населённые пункты — как в сообщении,
 * без географии — «Регион не указан».
 */
final class CatalogBuilderRegionDisplay
{
    public const MISSING = 'Регион не указан';

    public const MOSCOW_AGGLOMERATION = CatalogRegionBuckets::LABEL_MOSCOW;

    public const SPB_AGGLOMERATION = CatalogRegionBuckets::LABEL_SPB;

    public static function labelForBuilder(?Builder $builder, ?RussianRegionNormalizer $normalizer = null): string
    {
        if ($builder === null) {
            return self::MISSING;
        }

        $normalizer ??= app(RussianRegionNormalizer::class);

        $fromRegion = self::meaningfulTrim($builder->region);
        if ($fromRegion !== null) {
            return self::toDisplayLabel($fromRegion, $normalizer, keepRawIfUnknown: true);
        }

        $fromCity = self::meaningfulTrim($builder->location_city);
        if ($fromCity !== null) {
            return self::toDisplayLabel($fromCity, $normalizer, keepRawIfUnknown: true);
        }

        $fromLocationRegion = self::meaningfulTrim($builder->location_region);
        if ($fromLocationRegion !== null) {
            return self::toDisplayLabel($fromLocationRegion, $normalizer, keepRawIfUnknown: true);
        }

        return self::MISSING;
    }

    /**
     * Канон / сырая строка → подпись для колонки «Регион».
     */
    public static function toDisplayLabel(
        string $value,
        ?RussianRegionNormalizer $normalizer = null,
        bool $keepRawIfUnknown = false
    ): string {
        $normalizer ??= app(RussianRegionNormalizer::class);
        $trimmed = trim($value);
        if ($trimmed === '' || CatalogRegionOptions::isJunkRegionString($trimmed)) {
            return self::MISSING;
        }

        $canonical = $normalizer->normalize($trimmed) ?? ($keepRawIfUnknown ? $trimmed : null);
        if ($canonical === null || $canonical === '') {
            return self::MISSING;
        }

        if (CatalogRegionBuckets::isMoscowCanonical($canonical)) {
            return self::MOSCOW_AGGLOMERATION;
        }

        if (CatalogRegionBuckets::isSpbCanonical($canonical)) {
            return self::SPB_AGGLOMERATION;
        }

        return $canonical;
    }

    private static function meaningfulTrim(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $t = trim((string) $value);
        if ($t === '' || CatalogRegionOptions::isJunkRegionString($t)) {
            return null;
        }

        return $t;
    }
}
