<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Разбор JSON из текста ответа LLM (часто с markdown-ограждениями и пояснительным текстом).
 */
final class AiModelJsonReplyDecoder
{
    /**
     * @throws \Exception если не удалось получить ассоциативный массив из JSON
     */
    public static function decode(string $rawContent, string $logPrefix): array
    {
        $trimmed = trim($rawContent);
        if ($trimmed === '') {
            throw new \Exception($logPrefix.' Пустая строка после очистки ответа');
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($trimmed, $start, $end - $start + 1);
            $decoded = json_decode($slice, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $preview = function_exists('mb_substr')
            ? mb_substr($trimmed, 0, 600)
            : substr($trimmed, 0, 600);

        throw new \Exception(
            $logPrefix.' Ответ модели не является валидным JSON: '
            .json_last_error_msg().'. Начало ответа: '.$preview
        );
    }
}
