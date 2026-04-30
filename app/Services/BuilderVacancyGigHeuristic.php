<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Дополнение к ответу LLM: локальные модели часто дают «предложение услуги»
 * на посты найма со сменой (подработка), когда в тексте нет слов «вакансия»/«ищем».
 */
final class BuilderVacancyGigHeuristic
{
    /**
     * true — текст по смыслу набор людей на смену/подработку, а не оффер исполнителя.
     */
    public static function shouldOverrideAiServiceToVacancy(string $postText): bool
    {
        $t = self::normalize($postText);

        if ($t === '') {
            return false;
        }

        if (self::recruitmentWithApplicantContacts($t)) {
            return true;
        }

        if (self::shiftWithPayAfterAndFixedSum($t)) {
            return true;
        }

        if (self::explicitHeadcountOrShiftHire($t)) {
            return true;
        }

        return false;
    }

    private static function normalize(string $postText): string
    {
        $t = mb_strtolower(trim($postText));

        return str_replace('ё', 'е', $t);
    }

    /**
     * «Кто готов», просьба анкеты в ЛС и т.п.
     */
    private static function recruitmentWithApplicantContacts(string $t): bool
    {
        $recruit = (bool) preg_match(
            '/кто готов|кто может|кто свобод|кто на подработку|кто хочет подработать|'.
                'кто желает|отзовис|отзовитесь|откликнитесь|'.
                'нужны люди|нужен человек|нужен рабоч|нужна бригада|набор на|на см(ену|ены)/u',
            $t
        );
        if (! $recruit) {
            return false;
        }

        $askIdentity = (bool) preg_match(
            '/возраст|\bфио\b|ф\.и\.о|номер телефона|\bпаспорт/u',
            $t
        );

        $toLs = (bool) preg_match(
            '/(\b|^)в\s+лс\b|в\sлс\b|личк(\w+|е|у|ами)?|личные сообщени|писать в лс|'.
                'пишите в лс|пишем в лс|напишите в лс|в личные/u',
            $t
        );

        return $askIdentity && $toLs;
    }

    /**
     * Фиксированная сумма за N часов + оплата после смены/на карту — типичный найм со сменой.
     */
    private static function shiftWithPayAfterAndFixedSum(string $t): bool
    {
        $payAfter = (bool) preg_match(
            '/оплат(а|у)\s+по\s+окончани|после\s+работы|по\s+окончан/u',
            $t
        );
        if (! $payAfter) {
            return false;
        }

        $toCardOrTransfer = (bool) preg_match(
            '/на\s+карт|перевод|реквизит/u',
            $t
        );
        if (! $toCardOrTransfer) {
            return false;
        }

        $hoursAndMoney = (bool) preg_match(
            '/(\d+)\s*[₽р]+.*(\d+)\s*(час(ов)?|ч\.)\b|'.
                '(\d+)\s*(час(ов)?|ч\.).*\d+\s*[₽р]+/u',
            $t
        );

        $timeWindow = (bool) preg_match(
            '/с\s*\d{1,2}[.:]\d{2}\s*[\-–]\s*\d{1,2}[.:]\d{2}|\(\s*\d+\s*час/u',
            $t
        );

        return $hoursAndMoney && $timeWindow;
    }

    /**
     * Явное «N человек», «пара человек» к выходу + день/время.
     */
    private static function explicitHeadcountOrShiftHire(string $t): bool
    {
        $people = (bool) preg_match(
            '/\b\d+\s*челов\b|\b\d+\s*рабоч|'.
                'пару человек|два человека|один человек|нужн(а|ы|о)?\s+\d+\s*челов/u',
            $t
        );

        $timeMeet = (bool) preg_match(
            '/(\b|^)на\s+завтра|\bзавтра\s+к\s+\d{1,2}|к\s*\d{1,2}[.:]\d{2}|сбор\s+(в\s+)?\d{1,2}/u',
            $t
        );

        $meetRoute = (bool) preg_match(
            '/метро\b|\bавтобус\b|маршрутк|электричка/u',
            $t
        );

        return $people && ($timeMeet || $meetRoute);
    }
}
