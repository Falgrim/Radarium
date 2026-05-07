<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Консервативные текстовые эвристики для строителей (Builder):
 * снижают авто-публикацию в каталог, если пост похож на найм или короткий заказ без самопрезентации исполнителя.
 *
 * Дополнительно: «контракт объявления о подработке» — одновременно упоминание условий оплаты,
 * числового объёма работ и места или срока (типичный заказ/смена, не карточка исполнителя).
 */
final class CatalogPublicationBuilderNonServiceSignals
{
    public const REASON_HIRING = 'post_text_hiring_or_staffing_signal';

    public const REASON_CUSTOMER_SHORT = 'post_text_customer_request_without_offer';

    public const REASON_GIG_SPECIFICATION_BUNDLE = 'post_text_gig_payment_volume_place_or_date';

    /**
     * @return list<string> коды причин (пусто — эвристики не сработали)
     */
    public function reasons(string $postText): array
    {
        $text = $this->normalizeForMatch(mb_strtolower(trim($postText)));
        if ($text === '') {
            return [];
        }

        if ($this->matchesHiringOrStaffing($text)) {
            return [self::REASON_HIRING];
        }

        if ($this->matchesShortCustomerRequestWithoutPerformer($postText, $text)) {
            return [self::REASON_CUSTOMER_SHORT];
        }

        if ($this->matchesGigSpecificationBundle($text)) {
            return [self::REASON_GIG_SPECIFICATION_BUNDLE];
        }

        return [];
    }

    /**
     * Приводим типографские символы и невидимые пробелы — иначе \b и якоря могут не сработать.
     */
    private function normalizeForMatch(string $lowercased): string
    {
        $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $lowercased) ?? $lowercased;

        $t = str_replace(
            ['：', '－', '—', '–', '«', '»', '„', '“'],
            [':', '-', '-', '-', '«', '»', '"', '"'],
            $t
        );

        $t = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $t);

        return str_replace('ё', 'е', $t);
    }

    private function matchesHiringOrStaffing(string $lower): bool
    {
        if ($this->matchesTelegramJobLinkWithManualLaborSignals($lower)) {
            return true;
        }

        $patterns = [
            '/\bтребуются\b/u',
            '/\bтребуется\s+помощник/u',
            '/\bтребуется\s+(мастер|бригада|человек|люди|рабоч|монтажник|сварщик|маляр|гипсокартонщик|строител)/u',
            '/\bнужны\s+\d+\s*(человек|рабоч|мастер|специалист|cпециалист|бригада|маляр|гипсокартонщик)/u',
            '/\bнужны\s+(люди|рабоч)/u',
            '/\bнужен\s+(мастер|человек|рабоч|помощник|монтажник|сварщик)/u',
            '/\bнужна\s+бригада/u',
            '/\bищем\s+(людей|мастер|бригад|рабоч|исполнител|подсоб)/u',
            '/\bищу\s+исполнител/u',
            '/\bподработк/u',
            '/\bесть\s+работа/u',
            '/\bоплат[ау]\s+за\s+смену/u',
            '/\bвыход\s+(сегодня|завтра)\b/u',
            '/\d+\s*[-–—]\s*\d+\s*человек/u',
            '/\bбригада\s+\d+\s*[-–—]\s*\d+/u',
            '/\bкто\s+свободен/u',
            '/\bесть\s+кто\s+(?:на\s+)?(?:объект|смену)/u',
            '/\bаванс\s+на\s+\d/u',
            // Найм: выдача инструмента, жильё на объекте, сдельные выплаты «как вакансии»
            '/\bинструмент\s+выда(?:ётся|ется|ют|ем|ёте|ете)\b/u',
            '/\bинструмент\s+предоставляется\b/u',
            '/\bпредоставляется\s+инструмент/u',
            '/\bвыда(?:ёт|ет|ём|ем)(?:ся)?\s+инструмент/u',
            '/\bесть\s+проживание\b/u',
            '/\bвыплат(?:ы|а)\s+два\s+раза\s+в\s+месяц/u',
            '/\bвыплат(?:ы|а)\s+каждый\s+этаж/u',
            '/\bоплат[ау]\s+из\s+расч[её]та\b/u',
            '/\bработ[аы]\s+по\s+\d+\s*час/u',
            '/\b\d+\s+раз[аы]?\s+в\s+неделю\b/u',
            // «Ещё одного», набор на короткий выход
            '/\bещ[её]\s+одног[оа]\b/u',
            // Требования к кандидатам
            '/\bтрезвые\b/u',
            '/\bпатент\s*\(\s*если\s+не/u',
            // Сдельная оплата за физ. объём как в объявлениях о подработке
            '/\bоплат[ауио].{0,32}с\s+квадрат/u',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Рекрутинговые каналы / «объявления о работе»: t.me/... вместе с явными признаками найма полевых бригад.
     */
    private function matchesTelegramJobLinkWithManualLaborSignals(string $lower): bool
    {
        if (preg_match('/(?:^|[\s,.;:!?])(?:https?:\/\/)?(?:t|т)\.me\//iu', $lower) !== 1) {
            return false;
        }

        return (bool) preg_match(
            '/\d+\s*человек|ещ[её]\s+одног|писать\s+в\s+лс|пишите\s+в\s+лс|трезвые|'.
                'перфоратор|сбивать\s+штукатур|на\s+этаж|руб\.?\s*\/\s*квадра|\bр\.\s*с\s+квадра/u',
            $lower
        );
    }

    /**
     * Подработка/разовый заказ: одновременно есть и размер/условия оплаты (в т.ч. аванс, «по факту»),
     * и числовой объём работ, и геолокация/адрес или конкретные дата/время выхода.
     */
    private function matchesGigSpecificationBundle(string $lower): bool
    {
        if (! $this->hasGigPaymentTerms($lower)) {
            return false;
        }

        if (! $this->hasConcreteWorkVolume($lower)) {
            return false;
        }

        if (! $this->hasPlaceOrTimeConstraint($lower)) {
            return false;
        }

        return true;
    }

    private function hasGigPaymentTerms(string $t): bool
    {
        if (preg_match('/оплат[аио]\s+по\s+факту|по\s+факту\s+(?:выполн|сделан|сделанн)/u', $t) === 1) {
            return true;
        }

        if (preg_match('/\bаванс\b/u', $t) === 1) {
            return true;
        }

        if (preg_match(
            '/\d+(?:[\s,.]\d+)?\s*(?:тыс\.?|тысячи?)?\s*(?:₽|руб|руб\.|р\.)\b'.
            '|\d+\s*[₽руб]\b|\d+\s*руб\.?\s*\/\s*(?:м|м\s*[²2]|час|ч\.)\b'.
            '|\d+\s*р\.?\s*\/\s*(?:м|ч)/u',
            $t
        ) === 1) {
            return true;
        }

        if (preg_match('/\bцена\s*[:.]?\s*\d/u', $t) === 1) {
            return true;
        }

        if (preg_match('/\bоплат[аио].{0,40}\d.{0,6}(?:руб|₽|р\.|тыс)/u', $t) === 1) {
            return true;
        }

        return false;
    }

    private function hasConcreteWorkVolume(string $t): bool
    {
        if (preg_match(
            '/\d+(?:[\s,.]\d+)?\s*(?:м²|м2|м\s*²|м\s*2|кв\.?\s*м|м\.?\s*кв|[mм]2)\b/iu',
            $t
        ) === 1) {
            return true;
        }

        if (preg_match('/\bоб[ъь][её]м[аея]?\s*[:.]?\s*\d+/u', $t) === 1) {
            return true;
        }

        if (preg_match('/начальн.{0,16}об[ъь][её]м.{0,20}\d+/u', $t) === 1) {
            return true;
        }

        return false;
    }

    private function hasPlaceOrTimeConstraint(string $lower): bool
    {
        if (preg_match(
            '/\b(?:ул\.|улиц|наб\.|набережн|просп\.|проспект|переул|пр\.|шоссе|ш\.)\s/u',
            $lower
        ) === 1) {
            return true;
        }

        if (preg_match('/\bд\.?\s*\d+/u', $lower) === 1 || preg_match('/\bдом\s+\d+/u', $lower) === 1) {
            return true;
        }

        if (preg_match('/\b(?:метро|[мm]\.)\s*[а-яёa-z0-9«»\-]{1,40}/ui', $lower) === 1) {
            return true;
        }

        if (preg_match('/\bкаменн[а-яё]*\s+остров[а-яё]*\b/u', $lower) === 1) {
            return true;
        }

        if (preg_match('/\bг\.?\s+[а-яё\-]{2,30}\b/u', $lower) === 1) {
            return true;
        }

        if (preg_match(
            '/\b[а-яё]{4,22}\s+[-–]\s+[а-яё]{4,22}\b/u',
            $lower
        ) === 1) {
            return true;
        }

        if (preg_match(
            '#\d{1,2}[./]\d{1,2}[./]\d{2,4}|\d{1,2}\s+(?:январ|феврал|марта|апрел|ма[йя]|июн|июл|август|сентябр|октябр|ноябр|декабр)#u',
            $lower
        ) === 1) {
            return true;
        }

        if (preg_match(
            '/\b(?:завтра|послезавтра|с\s+завтрашн|на\s+завтра|сегодня\s+к|к\s+\d{1,2}[:.]\d{0,2}|смена\s+к)\b/u',
            $lower
        ) === 1) {
            return true;
        }

        if ($this->matchesSingleWordNonJobLocationLine($lower)) {
            return true;
        }

        return false;
    }

    /**
     * Одно слово в строке похоже на топоним (г. без «г.»), не заголовок работ.
     */
    private function matchesSingleWordNonJobLocationLine(string $lower): bool
    {
        foreach (preg_split("/\r\n|\n|\r/", $lower) as $line) {
            $line = trim($line);
            if (mb_strlen($line) < 5 || mb_strlen($line) > 42) {
                continue;
            }
            if (str_contains($line, ' ')) {
                continue;
            }
            if (! preg_match('/^[а-яёіґ\-]+$/u', $line)) {
                continue;
            }
            if (preg_match(
                '/работ|штукатур|маляр|потол|покрас|срочно|требу|нужн|заделка|слой|шпакл|грунт|шлифов|' .
                'покраск|аквапанель|сапфир|стеклохолст|шпаклев|безвоздушн|укладк|демонтаж|монтаж|электрик/u',
                $line
            )) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Короткое сообщение-заказ («нужно смонтировать…», «нужно произвести укладку…») без маркеров исполнителя.
     */
    private function matchesShortCustomerRequestWithoutPerformer(string $original, string $lower): bool
    {
        if (mb_strlen($original) > 420) {
            return false;
        }

        if ($this->hasPerformerOfferMarkers($lower)) {
            return false;
        }

        $order = '/(?:^|[\s,.;:!?—\-])(?:нужно|надо|срочно\s+нужно)\s+(?:смонтировать|установить|сделать|снять|демонтировать|провести|починить|построить|заменить|произвести|выполнить)\b/u';

        return preg_match($order, $lower) === 1;
    }

    private function hasPerformerOfferMarkers(string $lower): bool
    {
        $markers = [
            '/\bвыполня(?:ю|им|ем)\b/u',
            '/\bдела(?:ю|ем)\b/u',
            '/\bоказыва(?:ю|ем)\b/u',
            '/\bмы\s+бригада/u',
            '/\bя\s+мастер/u',
            '/\bя\s+электрик/u',
            '/\bя\s+сантехник/u',
            '/\bищем\s+заказ/u',
            '/\bищу\s+заказ/u',
            '/\bищу\s+объект/u',
            '/\bвозьму\s+объ/u',
            '/\bвозьмём\b/u',
            '/\bвозьмем\b/u',
            '/\bработаем\s+по/u',
            '/\bвыезжаем\b/u',
            '/\bсвободн[ая]\s+бригада/u',
            '/\bсвободен.{0,40}(?:объ|заказ|выезд)/u',
        ];

        foreach ($markers as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }
}
