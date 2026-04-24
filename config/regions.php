<?php

/**
 * Плоский справочник для выпадающих списков (источник сообщений, фильтры каталога).
 * Ключ = значение — каноническое название региона/города.
 *
 * @return array<string, string>
 */
$geo = require __DIR__.'/russian_geography.php';

$citiesPath = __DIR__.'/../app/Data/russian_cities_100k.json';
$cities = is_readable($citiesPath)
    ? json_decode(file_get_contents($citiesPath), true, 512, JSON_THROW_ON_ERROR)
    : [];

$keys = array_values(array_unique(array_merge($geo['federal_subjects'], $cities)));
sort($keys, SORT_STRING);

return array_combine($keys, $keys);
