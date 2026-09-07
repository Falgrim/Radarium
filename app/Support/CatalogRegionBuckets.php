<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Агрегированные «корзины» регионов для фильтра и подписей каталога.
 * Канонические значения — как в {@see \App\Services\RussianRegionNormalizer} / config/russian_geography.php.
 */
final class CatalogRegionBuckets
{
    public const FILTER_MOSCOW = 'moscow_mo';

    public const FILTER_SPB = 'spb_lo';

    public const FILTER_EXCEPT_MSK_SPB = 'except_msk_spb';

    public const LABEL_MOSCOW = 'Москва и МО';

    public const LABEL_SPB = 'Санкт-Петербург и ЛО';

    public const LABEL_EXCEPT_MSK_SPB = 'Все регионы кроме МСК и СПБ';

    /**
     * Значения поля region (карточка / канал), относящиеся к агломерации Москвы.
     *
     * @return list<string>
     */
    public static function moscowCanonicalRegions(): array
    {
        return [
            'Москва',
            'Московская область',
            self::LABEL_MOSCOW,
        ];
    }

    /**
     * @return list<string>
     */
    public static function spbCanonicalRegions(): array
    {
        return [
            'Санкт-Петербург',
            'Ленинградская область',
            self::LABEL_SPB,
        ];
    }

    /**
     * Подстроки названия источника (api_channels.title), нижний регистр.
     *
     * @return list<string>
     */
    public static function moscowChannelTitleNeedles(): array
    {
        return [
            'москва',
            'московск',
            'подмосков',
            'мск',
        ];
    }

    /**
     * @return list<string>
     */
    public static function spbChannelTitleNeedles(): array
    {
        return [
            'санкт-петербург',
            'санкт петербург',
            'петербург',
            'ленинград',
            'спб',
            'питер',
        ];
    }

    /**
     * Фиксированный выпадающий список агрегированного режима.
     *
     * @return array<string, string> value => label
     */
    public static function aggregatedSelectOptions(): array
    {
        return [
            self::FILTER_MOSCOW => self::LABEL_MOSCOW,
            self::FILTER_SPB => self::LABEL_SPB,
            self::FILTER_EXCEPT_MSK_SPB => self::LABEL_EXCEPT_MSK_SPB,
        ];
    }

    public static function isMoscowCanonical(?string $region): bool
    {
        if ($region === null) {
            return false;
        }

        return in_array(trim($region), self::moscowCanonicalRegions(), true);
    }

    public static function isSpbCanonical(?string $region): bool
    {
        if ($region === null) {
            return false;
        }

        return in_array(trim($region), self::spbCanonicalRegions(), true);
    }
}
