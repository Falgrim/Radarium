<?php

declare(strict_types=1);

/**
 * Фильтр «Регион» в публичном каталоге строителей и проектировщиков.
 *
 * mode=aggregated — три пункта: Москва и МО / СПб и ЛО / все кроме МСК и СПБ.
 * mode=legacy — прежний длинный список канонических регионов (откат).
 *
 * Откат на проде: CATALOG_REGION_FILTER_MODE=legacy и sh gitupdate.sh (или config:clear).
 */
return [
    'mode' => env('CATALOG_REGION_FILTER_MODE', 'aggregated'),
];
