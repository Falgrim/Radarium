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

        if (self::lsRecruitmentWithHeadcount($t)) {
            return true;
        }

        if (self::partTimeHourlyAssistantSchedule($t)) {
            return true;
        }

        if (self::employerHousingToolsOrPayroll($t)) {
            return true;
        }

        if (self::telegramJobLinkWithFieldWork($t)) {
            return true;
        }

        return false;
    }

    private static function normalize(string $postText): string
    {
        $t = mb_strtolower(trim($postText));
        $t = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $t);

        return str_replace('ё', 'е', $t);
    }

    /**
     * В тексте ссылка на tg-канал и сдельные/полевые работы (не карточка подрядчика).
     */
    private static function telegramJobLinkWithFieldWork(string $t): bool
    {
        if (preg_match('/(?:^|[\s,.;:!?])(?:https?:\/\/)?(?:t|т)\.me\//iu', $t) !== 1) {
            return false;
        }

        return (bool) preg_match(
            '/\d+\s*человек|ещ[её]\s+одног|писать\s+в\s+лс|пишите\s+в\s+лс|трезвые|перфоратор|сбивать\s+штукатур/u',
            $t
        );
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
            '/\b\d+\s*[-–—]\s*\d+\s*человек\b|\b\d+\s*челов\b|\b\d+\s*рабоч|'.
                'пару человек|два человека|один человек|нужн(а|ы|о)?\s+\d+\s*челов|ещ[её]\s+одног/u',
            $t
        );

        $timeMeet = (bool) preg_match(
            '/(\b|^)на\s+завтра|с\s+завтрашн|завтрашн|\bзавтра\s+к\s+\d{1,2}|к\s*\d{1,2}[.:]\d{0,2}\b|сбор\s+(в\s+)?\d{1,2}/u',
            $t
        );

        $meetRoute = (bool) preg_match(
            '/метро\b|\bавтобус\b|маршрутк|электричка/u',
            $t
        );

        return $people && ($timeMeet || $meetRoute);
    }

    /**
     * «Пишать в лс» + численность / «ещё одного» — типичный короткий набор на объект.
     */
    private static function lsRecruitmentWithHeadcount(string $t): bool
    {
        if (! preg_match('/писать\s+в\s+лс|пишите\s+в\s+лс|пишем\s+в\s+лс/u', $t)) {
            return false;
        }

        return (bool) preg_match(
            '/\b\d+\s+человек[а]?\b|\bещ[её]\s+одног|два\s+человека/u',
            $t
        );
    }

    /**
     * Почасовая ставка + фиксированные часы смены + недельный график (помощник, не прайс бригады).
     */
    private static function partTimeHourlyAssistantSchedule(string $t): bool
    {
        if (! preg_match('/\d+\s*руб\.?\s*\/\s*час/u', $t)) {
            return false;
        }
        if (! preg_match('/работ[аы]\s+по\s+\d+\s*час/u', $t)) {
            return false;
        }

        return (bool) preg_match('/\d+\s+раз[аы]?\s+в\s+неделю/u', $t);
    }

    /**
     * Пакет «как у работодателя»: жильё на объекте + выдача инструмента или выплаты два раза в месяц.
     */
    private static function employerHousingToolsOrPayroll(string $t): bool
    {
        $housing = (bool) preg_match('/\bесть\s+проживание\b/u', $t);
        $tools = (bool) preg_match(
            '/\bинструмент\s+выда(?:ётся|ется|ют|ем)|\bинструмент\s+предоставляется|'.
                '\bпредоставляется\s+инструмент|\bвыда(?:ёт|ет)(?:ся)?\s+инструмент/u',
            $t
        );
        $biweeklyPay = (bool) preg_match('/\bвыплат(?:ы|а)\s+два\s+раза\s+в\s+месяц/u', $t);

        return $housing && ($tools || $biweeklyPay);
    }
}
