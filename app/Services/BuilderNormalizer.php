<?php

namespace App\Services;

use App\Enum\BuilderTypeEnum;

class BuilderNormalizer
{
    /**
     * Нормализация типа сообщения с адаптером обратной совместимости.
     * Старые значения: 'резюме', 'предоставление услуги', 'предложение услуг'
     * маппируются в BuilderTypeEnum::Service.
     */
    public static function normalizeType(string $raw): BuilderTypeEnum
    {
        $raw = mb_strtolower(trim($raw));

        $serviceAliases = [
            'предложение услуги',
            'предложение услуг',
            'предоставление услуги',
            'резюме',
        ];

        if (in_array($raw, $serviceAliases)) {
            return BuilderTypeEnum::Service;
        }

        if ($raw === 'вакансия') {
            return BuilderTypeEnum::Vacancy;
        }

        return BuilderTypeEnum::Junk;
    }

    /**
     * Нормализация типа исполнителя.
     * Приоритет: поле performer_type от AI (лёгкая нормализация).
     * Если пусто — нормализовать из performer_type_raw.
     */
    public static function normalizePerformerType(?string $raw): string
    {
        if (empty($raw)) {
            return 'не указан';
        }

        $lower = mb_strtolower(trim($raw));

        if (str_contains($lower, 'бригад') || str_contains($lower, 'команд')) {
            return 'бригада';
        }

        if (str_contains($lower, 'напарник') || str_contains($lower, 'вместе') || str_contains($lower, 'партнер')) {
            return 'работа с напарником';
        }

        if (
            str_contains($lower, 'один') || str_contains($lower, 'сам ') ||
            str_contains($lower, 'индивидуал') || str_contains($lower, 'самостоятельно')
        ) {
            return 'индивидуал';
        }

        return 'не указан';
    }

    /**
     * Нормализация юридической формы.
     * mb_strtolower + trim перед всеми сравнениями.
     */
    public static function normalizeLegalForm(?string $raw): string
    {
        if (empty($raw)) {
            return 'не указано';
        }

        $lower = mb_strtolower(trim($raw));

        if (in_array($lower, ['не указано', 'не указан', 'нет', '-', 'н/д'])) {
            return 'не указано';
        }

        if (in_array($lower, ['сз', 'самозанятый', 'самозанятая', 'самозанятость'])) {
            return 'СЗ';
        }
        if (in_array($lower, ['ип', 'индивидуальный предприниматель'])) {
            return 'ИП';
        }
        if ($lower === 'ооо') {
            return 'ООО';
        }
        if (in_array($lower, ['ао', 'зао', 'пао'])) {
            return 'АО';
        }

        return 'другое';
    }

    /**
     * Нормализация типов объектов.
     * Только типы ОБЪЕКТОВ — не виды работ.
     * фасад, кровля, отделка — это виды работ → игнорировать.
     * Возвращает дедуплицированный массив.
     */
    public static function normalizeObjectTypes(array $raw): array
    {
        $workTypes = ['фасад', 'кровля', 'отделка', 'ремонт', 'монтаж', 'демонтаж'];
        $normalized = [];

        foreach ($raw as $item) {
            $lower = mb_strtolower(trim($item));

            foreach ($workTypes as $workType) {
                if (str_contains($lower, $workType)) {
                    continue 2;
                }
            }

            if (in_array($lower, ['кв', 'квартира', 'квартиры', 'апартаменты', 'студия', 'жк'])) {
                $normalized[] = 'квартира';
            } elseif (in_array($lower, ['дом', 'коттедж', 'частный дом', 'таунхаус', 'дача'])) {
                $normalized[] = 'дом';
            } elseif (in_array($lower, ['коммерция', 'офис', 'тц', 'торговый центр', 'магазин', 'ресторан'])) {
                $normalized[] = 'коммерция';
            } elseif (str_contains($lower, 'промышлен') || in_array($lower, ['завод', 'склад', 'производство'])) {
                $normalized[] = 'промышленный объект';
            } elseif (str_contains($lower, 'общественн') || in_array($lower, ['школа', 'больница', 'гостиница', 'отель'])) {
                $normalized[] = 'общественный объект';
            } else {
                $normalized[] = trim($item);
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Очистка массива оборудования/навыков.
     * - trim + обрезка до 150 символов
     * - фильтр: минимум 4 символа (убирает мусор и аббревиатуры-однобуквы)
     * - максимум 10 элементов
     */
    public static function cleanEquipmentSkills(array $raw): array
    {
        $cleaned = array_map(
            fn($s) => trim(mb_substr(trim($s), 0, 150)),
            $raw
        );

        $filtered = array_values(array_filter(
            $cleaned,
            fn($s) => mb_strlen($s) > 3
        ));

        return array_slice($filtered, 0, 10);
    }

    /**
     * Извлечение числа из строки цены.
     * AI часто вернёт "5000 руб", "от 3000", "~150 т.р." — нужно вытащить число.
     * Если после очистки пусто или не числовое — вернуть null.
     * Возвращает сумму в рублях (целое число).
     */
    public static function cleanPrice(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.,]/', '', $raw);
        $cleaned = str_replace(',', '.', $cleaned);

        if ($cleaned === '' || !is_numeric($cleaned)) {
            return null;
        }

        return (int)round((float)$cleaned);
    }

    // TODO (Stage 2): перейти на DI через app(BuilderNormalizer::class),
    // когда понадобится инжектировать зависимости (словари, конфиги).

    // TODO (Stage 2): добавить поле price_currency (VARCHAR, nullable) в builders/specialists/company_jobs.
    // AI должен определять валюту из контекста сообщения (руб/$/€/USD/EUR).
    // По умолчанию — рубли. Случаи с долларами и евро редки, но встречаются.
}
