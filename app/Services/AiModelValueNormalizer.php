<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Приведение значений из JSON-ответа ИИ к типу поля карточки.
 *
 * Модель не держит обещанную промптом форму: по скалярному полю может прийти список или объект,
 * по полю-справочнику — готовая строка. Без нормализации такой ответ ронял разбор поста
 * («foreach() argument must be of type array|object, string given», «Array to string conversion»),
 * и пост уходил в Error вместо карточки.
 */
final class AiModelValueNormalizer
{
    private const SEPARATOR = '; ';

    /**
     * Скалярное поле карточки: строка, список строк или вложенная структура сводятся к одной строке.
     */
    public static function toText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $parts = [];
        array_walk_recursive($value, static function ($leaf) use (&$parts): void {
            if ($leaf === null || is_bool($leaf) || is_array($leaf)) {
                return;
            }

            $text = trim((string) $leaf);
            if ($text !== '') {
                $parts[] = $text;
            }
        });

        return implode(self::SEPARATOR, $parts);
    }

    /**
     * Поле-справочник вида «ключ: значение» (contact_info): объект с контактами, список таких
     * объектов или уже готовая строка.
     */
    public static function toContactText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $groups = array_is_list($value) ? $value : [$value];

        $parts = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                $text = self::toText($group);
                if ($text !== '') {
                    $parts[] = $text;
                }

                continue;
            }

            foreach ($group as $key => $item) {
                $text = self::toText($item);
                if ($text === '') {
                    continue;
                }

                $parts[] = is_int($key) ? $text : $key.': '.$text;
            }
        }

        return implode(self::SEPARATOR, $parts);
    }
}
