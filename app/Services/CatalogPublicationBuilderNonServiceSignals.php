<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Консервативные текстовые эвристики для строителей (Builder):
 * снижают авто-публикацию в каталог, если пост похож на найм или короткий заказ без самопрезентации исполнителя.
 */
final class CatalogPublicationBuilderNonServiceSignals
{
    public const REASON_HIRING = 'post_text_hiring_or_staffing_signal';

    public const REASON_CUSTOMER_SHORT = 'post_text_customer_request_without_offer';

    /**
     * @return list<string> коды причин (пусто — эвристики не сработали)
     */
    public function reasons(string $postText): array
    {
        $text = mb_strtolower(trim($postText));
        if ($text === '') {
            return [];
        }

        if ($this->matchesHiringOrStaffing($text)) {
            return [self::REASON_HIRING];
        }

        if ($this->matchesShortCustomerRequestWithoutPerformer($postText, $text)) {
            return [self::REASON_CUSTOMER_SHORT];
        }

        return [];
    }

    private function matchesHiringOrStaffing(string $lower): bool
    {
        $patterns = [
            '/\bтребуются\b/u',
            '/\bтребуется\s+помощник/u',
            '/\bтребуется\s+(мастер|бригада|человек|люди|рабоч|монтажник|сварщик|маляр|гипсокартонщик)/u',
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
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Короткое сообщение-заказ («нужно смонтировать… в лс») без маркеров исполнителя.
     */
    private function matchesShortCustomerRequestWithoutPerformer(string $original, string $lower): bool
    {
        if (mb_strlen($original) > 220) {
            return false;
        }

        if ($this->hasPerformerOfferMarkers($lower)) {
            return false;
        }

        $order = '/(?:^|[\s,.;:!?—\-])(?:нужно|надо|срочно\s+нужно)\s+(?:смонтировать|установить|сделать|снять|демонтировать|провести|починить|построить|заменить)\b/u';

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
