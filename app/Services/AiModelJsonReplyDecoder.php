<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiContentRefusalException;

/**
 * Разбор JSON из текста ответа LLM (часто с markdown-ограждениями и пояснительным текстом).
 */
final class AiModelJsonReplyDecoder
{
    /**
     * @throws AiContentRefusalException при отказе safety
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

        if (self::looksLikeSafetyRefusal($trimmed)) {
            throw new AiContentRefusalException(
                $logPrefix.' Отказ модели (safety): '.$preview
            );
        }

        throw new \Exception(
            $logPrefix.' Ответ модели не является валидным JSON: '
            .json_last_error_msg().'. Начало ответа: '.$preview
        );
    }

    public static function looksLikeSafetyRefusal(string $text): bool
    {
        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($text)
            : strtolower($text);

        $needles = [
            'не могу обсуждать эту тему',
            'давайте поговорим о чём-нибудь ещё',
            'давайте поговорим о чем-нибудь еще',
            'i cannot discuss',
            'i can\'t discuss',
            'i can\'t assist',
            'i cannot assist',
            'as an ai',
            'не могу помочь с этим',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
