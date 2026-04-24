<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * Как cast "array", но при битом JSON в колонке возвращает [] вместо исключения
 * (иначе MoonShine / dehydrate падают с 500 на форме редактирования).
 */
final class LenientJsonArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null || $value === '') {
            return json_encode([], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return json_encode([], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            try {
                json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

                return $trimmed;
            } catch (JsonException) {
                return json_encode([], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        return json_encode([], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
