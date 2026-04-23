<?php

declare(strict_types=1);

namespace App\Data;

use JsonException;

/**
 * Справочник специализаций builders: slug только для связки parent при сиде.
 * title — каноническое значение для LLM (поле specialities в JSON).
 *
 * Данные собраны из DOC/builder_specialities_updated.xlsx (лист «Специализации») и
 * доп. ключей (scripts/build_builder_speciality_json.py). При смене xlsx: обновить JSON.
 *
 * @return list<array{slug: string, parent_slug: ?string, title: string, short_name: string, group_title: string, key_words: list<string>}>
 */
final class BuilderSpecialityDictionaryData
{
    public static function rows(): array
    {
        $path = __DIR__.'/builder_speciality_dictionary.json';
        if (! is_readable($path)) {
            throw new \RuntimeException('Отсутствует файл '.$path);
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \RuntimeException('Некорректный JSON справочника: '.$e->getMessage(), 0, $e);
        }

        if (! isset($data['rows']) || ! is_array($data['rows'])) {
            throw new \RuntimeException('В JSON нет ключа rows');
        }

        return $data['rows'];
    }
}
