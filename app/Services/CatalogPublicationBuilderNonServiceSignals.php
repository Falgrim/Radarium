<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Консервативные текстовые эвристики для строителей (Builder):
 * снижают авто-публикацию в каталог, если пост похож на найм или короткий заказ без самопрезентации исполнителя.
 *
 * Дополнительно: «контракт объявления о подработке» — одновременно упоминание условий оплаты,
 * числового объёма работ и места или срока (типичный заказ/смена, не карточка исполнителя).
 *
 * Плюс жёсткий отсев «визуального мусора»: эмодзи-стены, почти нет кириллицы, латинский клавиатурный мусор —
 * в одной логике с Pass 1 / Pass 2 промптами (см. config/builder_ai_pipeline.php).
 */
final class CatalogPublicationBuilderNonServiceSignals
{
    public const REASON_HIRING = 'post_text_hiring_or_staffing_signal';

    public const REASON_CUSTOMER_SHORT = 'post_text_customer_request_without_offer';

    public const REASON_GIG_SPECIFICATION_BUNDLE = 'post_text_gig_payment_volume_place_or_date';

    /** Короткий заказ: вид работ + объём + ставка за м² без самопрезентации исполнителя. */
    public const REASON_COMPACT_UNIT_PRICE_WORK_ORDER = 'post_text_compact_unit_price_work_order';

    /** Спам, эмодзи-флуд или недостаточно русских букв для карточки исполнителя. */
    public const REASON_SPAM_OR_LOW_LEXICAL_SIGNAL = 'post_text_spam_or_low_lexical_signal';

    /** Проектирование, визуализация, веб-дизайн, чертёж Revit/BIM — не полевые строительные работы. */
    public const REASON_NON_FIELD_CONSTRUCTION = 'post_text_non_field_construction_service';

    /**
     * Причины, при которых пайплайн обрывается до создания Builder (как при найме).
     *
     * @return list<string>
     */
    public static function pipelineHardBlockReasonCodes(): array
    {
        return [
            self::REASON_HIRING,
            self::REASON_SPAM_OR_LOW_LEXICAL_SIGNAL,
            self::REASON_NON_FIELD_CONSTRUCTION,
        ];
    }

    /**
     * Текст относится к проектированию/визуализации/веб-дизайну — перенаправить в каталог проектировщиков.
     */
    public function shouldRedirectToSpecialist(string $postText): bool
    {
        return in_array(self::REASON_NON_FIELD_CONSTRUCTION, $this->reasons($postText), true);
    }

    /**
     * @return list<string> коды причин (пусто — эвристики не сработали)
     */
    public function reasons(string $postText): array
    {
        $text = $this->normalizeForMatch(mb_strtolower(trim($postText)));
        if ($text === '') {
            return [];
        }

        if ($this->matchesSpamOrLowLexicalSignal($postText)) {
            return [self::REASON_SPAM_OR_LOW_LEXICAL_SIGNAL];
        }

        if ($this->matchesNonFieldConstructionService($postText, $text)) {
            return [self::REASON_NON_FIELD_CONSTRUCTION];
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

        if ($this->matchesCompactUnitPriceWorkOrder($postText, $text)) {
            return [self::REASON_COMPACT_UNIT_PRICE_WORK_ORDER];
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

        $t = str_replace('ё', 'е', $t);

        // Смайлы/символы сразу перед кириллицей: «🍫Требуется» — \b перед «требуется» не срабатывает
        $t = preg_replace('/([\p{So}\p{Sk}])(?=[\p{Cyrillic}])/u', '$1 ', $t) ?? $t;
        // Только горизонтальные пробелы: переносы строк нужны для эвристик «одно слово — топоним» в строке
        $t = preg_replace('/\R/u', "\n", $t) ?? $t;
        $t = preg_replace('/\n{2,}/u', "\n", $t) ?? $t;
        $t = preg_replace('/\h+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    /**
     * Услуги проектировщиков, визуализаторов, веб/UI-дизайнеров, чертёжников Revit/BIM —
     * не целевые для каталога «Строительство» (полевые бригады и мастера на объекте).
     */
    private function matchesNonFieldConstructionService(string $original, string $lower): bool
    {
        if ($this->looksLikeFieldConstructionPerformer($lower)) {
            return false;
        }

        $strongPatterns = [
            '/\b(?:ux\s*\/\s*ui|ui\s*\/\s*ux|uxui)\b/u',
            '/\b(?:ux|ui)\s*[-\s]?(?:\/\s*)?(?:ux|ui)?\s*дизайн/u',
            '/\bдизайнер\s+сайт/u',
            '/\b(?:создам|разработаю|сверстаю)\b.{0,48}\b(?:сайт|лендинг|интернет[-\s]магазин)\b/u',
            '/\b(?:лендинг|интернет[-\s]магазин)\b.{0,80}\b(?:тильд|tilda|tap\s*top|тап\s*топ)\b/u',
            '/\b#визуализатор\b/u',
            '/\b(?:3d|3\s*d)\s*[-\s]?визуализ/u',
            '/\bвизуализатор\b/u',
            '/\b3ds\s*max\b/u',
            '/\b(?:corona|v[\-\s]?ray)\b/u',
            '/\b(?:ракурс|круг\w*\s+правок)\b/u',
            '/\bрабоч(?:ая|ую|ей|e)\s+документаци/u',
            '/\bдизайн[-\s]проект/u',
            '/\b(?:тильд|tilda)\b/u',
            '/\bbehance\.net\b/u',
            '/\b(?:команда|опытная\s+команда)\s+проектировщик/u',
            '/\b(?:низко|высоко)полигональн/u',
            '/\bifc\b.{0,24}\b(?:агр|agr)\b/u',
            '/\b(?:мка|mka)\b/u',
            '/\b(?:лазерное\s+сканирование|3d\s+видео\s+обл[её]т)\b/u',
            '/\b360\s*[°º]?\s*панорам/u',
        ];

        foreach ($strongPatterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        if ($this->countProjectDesignSectionAbbreviations($lower) >= 3) {
            return true;
        }

        $officeToolPatterns = [
            '/\b#(?:чертежник|revit|ревит|bim|визуализатор|дизайн)\b/u',
            '/\b(?:revit|ревит|autocad|архикад|archicad)\b/u',
            '/\b#чертежник\b/u',
            '/\bчертежник\b/u',
            '/\bbim[-\s]?(?:разработ|проект|manager|менеджер)\b/u',
        ];

        $officeHits = 0;
        foreach ($officeToolPatterns as $re) {
            if (preg_match($re, $lower) === 1) {
                $officeHits++;
            }
        }

        if ($officeHits >= 2) {
            return true;
        }

        if ($officeHits >= 1 && preg_match('/\b(?:#услуга|предоставля(?:ю|ем)|предлага(?:ю|ем)|разрабатыва(?:ю|ем)|выполня(?:ю|ем))\b/u', $lower) === 1) {
            return true;
        }

        if (preg_match('/\b(?:revit|ревит|bim)\b/u', $lower) === 1
            && preg_match('/\b(?:чертеж|рабоч(?:ая|ую)|дизайн[-\s]проект|проект(?:ир|н)|визуализ)\b/u', $lower) === 1) {
            return true;
        }

        return false;
    }

    private function looksLikeFieldConstructionPerformer(string $lower): bool
    {
        $patterns = [
            '/\b(?:бригада|мы|я)\b.{0,96}\b(?:выполн|делаем|делаю|монтиру|отделыва|штукатур|маляр|кладк|демонтаж|плитк|покрас|гипсокартон|кровл|фасад|ремонт|газоблок|пеноблок)\b/u',
            '/\b(?:маляр|штукатур|плиточник|каменщик|электрик|сантехник|монтажник|разнорабоч|гипсокартонщик|кровельщик|фасадчик|отделочник)\b.{0,80}\b(?:ищ(?:у|ем)|предлага(?:ю|ем)|выполн|дела(?:ю|ем)|готов)\b/u',
            '/\b(?:возьм(?:ём|ем)|возьму)\s+объ/u',
            '/\b(?:уборк[аи]\s+снег|очистк[аи]\s+снег)\b/u',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    private function countProjectDesignSectionAbbreviations(string $lower): int
    {
        if (preg_match_all('/\b(?:раздел\s+)?(?:гп|ар|кр|кж|км|эс|сс|ов|тс|вк|нв|гсн|гсв|пpr|ппр)\b/u', $lower, $matches) !== false) {
            return count(array_unique($matches[0]));
        }

        return 0;
    }

    /**
     * Согласовано с промптами Pass 1/2: нет связного русскоязычного предложения услуг.
     */
    private function matchesSpamOrLowLexicalSignal(string $original): bool
    {
        $trimmed = trim($original);
        $len = mb_strlen($trimmed);
        if ($len < 16) {
            return false;
        }

        $withoutUrls = preg_replace('#https?://[^\s]+#iu', ' ', $trimmed);
        $withoutUrls = preg_replace('/\s+/u', ' ', (string) $withoutUrls);
        $withoutUrls = trim($withoutUrls);

        $cyr = $this->countCyrillicLetters($withoutUrls);
        $emojiSym = $this->countEmojiAndOrnamentalSymbols($withoutUrls);
        $latin = $this->countLatinLetters($withoutUrls);

        if ($len >= 22 && $cyr === 0) {
            return true;
        }

        if ($len >= 36 && $cyr < 10) {
            return true;
        }

        if ($len >= 28 && $emojiSym >= 14 && $cyr < 12) {
            return true;
        }

        if ($len >= 24 && $emojiSym >= 8 && $cyr < 6) {
            return true;
        }

        if ($len >= 45 && $latin >= 48 && $cyr < 5) {
            return true;
        }

        return false;
    }

    private function countCyrillicLetters(string $s): int
    {
        return preg_match_all('/\p{Cyrillic}/u', $s) ?: 0;
    }

    private function countLatinLetters(string $s): int
    {
        return preg_match_all('/\p{Latin}/u', $s) ?: 0;
    }

    private function countEmojiAndOrnamentalSymbols(string $s): int
    {
        return preg_match_all('/[\p{So}\p{Sk}]/u', $s) ?: 0;
    }

    private function matchesHiringOrStaffing(string $lower): bool
    {
        if ($this->looksLikePerformerOfferOrJobSeeker($lower)) {
            return false;
        }

        if ($this->matchesUnambiguousCustomerHiring($lower)) {
            return true;
        }

        if ($this->matchesTelegramJobLinkWithManualLaborSignals($lower)) {
            return true;
        }

        return $this->matchesContextualStaffingHeuristicSignals($lower);
    }

    /**
     * Явный найм со стороны заказчика: не отменяется маркерами «ищу работу» / «предлагаю услуги».
     */
    private function matchesUnambiguousCustomerHiring(string $lower): bool
    {
        $patterns = [
            '/\bтребуются\b/u',
            '/\bтребуется\s+помощник/u',
            '/(?<!\bкому\s)требуется\s+(мастер|бригада|человек|люди|рабоч|монтажник|сварщик|маляр|гипсокартонщик|строител|'.
                'плиточник|каменщик|подрядчик|кровель|фасадчик|электрик|сантехник|универсал|демонтаж|уборк|штукатур)/u',
            '/\bтребуется\s+подрядчик/u',
            '/(?<!\p{L})требуется.{0,120}строител/u',
            '/\bкто\s+занимается\b/u',
            '/\bкто\s+занимается\b.{0,260}\bбригада\s+из\s+\d{1,3}\s*человек\b/u',
            '/\bесть\s+.{0,24}объем.{0,220}\bбригада\s+из\s+\d{1,3}\s*человек\b/u',
            '/\bбригада\s+из\s+\d{1,3}\s*человек.{0,160}(?:жиль|жилье|суточн)/u',
            '/\bтолько\s+ип\s+и\s+ооо\b/u',
            '/\bаванс\s+\d{1,3}\s*%/u',
            '/\bчастных\s+лиц\b.{0,80}(?:не\s+беспокоить|просьба)/u',
            '/\bнужны\s+\d+\s*(человек|рабоч|мастер|специалист|cпециалист|бригада|маляр|гипсокартонщик)/u',
            '/\bнужны\s+(люди|рабоч)/u',
            '/\bнужен\s+(мастер|человек|рабоч|помощник|монтажник|сварщик)/u',
            // «Нужен 1 человек» — между «нужен» и «человек» часто стоит число; без этого паттерна найм не ловился
            '/\bнужен\s+\d{1,3}\s*человек/u',
            '/\bнужна\s+\d{1,3}\s*человек/u',
            '/\bнужно\s+\d{1,3}\s*[-–—]\s*\d{1,3}\s*человек/u',
            '/\bнужна\s+бригада/u',
            '/\bнужна\s+на\s+завтра\s+подработк/u',
            '/\bищем\s+(людей|мастер|бригад|рабоч|исполнител|подсоб)/u',
            '/\bищу\s+исполнител/u',
            '/\bесть\s+подработк/u',
            '/\bтребуются\b.{0,100}\b(моляр|гипсокартонщик|штукатур|покрас)/u',
            '/\b(моляр|гипсокартонщики|гипсокартонщик)\b.{0,120}\bтребуются\b/u',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Сигналы найма/смены, которые часто встречаются и у исполнителей («ищу подработку», «бригада 10–15 человек»).
     */
    private function matchesContextualStaffingHeuristicSignals(string $lower): bool
    {
        $patterns = [
            '/\bесть\s+работа/u',
            '/\bнужн[аоы]\s+подработк/u',
            '/\bоплат[ау]\s+за\s+смену/u',
            '/\bвыход\s+(сегодня|завтра)\b/u',
            '/\d+\s*[-–—]\s*\d+\s*человек/u',
            '/\bбригада\s+\d+\s*[-–—]\s*\d+/u',
            '/\bбригада\b.{0,80}\d+\s*[-–—]\s*\d+\s*человек/u',
            '/\bкто\s+свободен/u',
            '/\bесть\s+кто\s+(?:на\s+)?(?:объект|смену)/u',
            '/\bаванс\s+на\s+\d/u',
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
            '/\bещ[её]\s+одног[оа]\b/u',
            '/\bтрезвые\b/u',
            '/\bпатент\s*\(\s*если\s+не/u',
            '/\bоплат[ауио].{0,32}с\s+квадрат/u',
            '/\bтуры\s+есть\b/u',
            '/\bесть\s+\d+\s+колонн\b/u',
            // Оплата «день в день» за сделанный объём — типичный заказчик/субподряд, не прайс бригады «мы делаем»
            '/расчет\s+сразу.{0,25}день\s+в\s+день.{0,50}за\s+проделан/u',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Исполнитель в каталоге: предлагает услуги, ищет заказ/работу, резюме, BIM/удалёнка.
     */
    private function looksLikePerformerOfferOrJobSeeker(string $lower): bool
    {
        if ($this->hasPerformerOfferMarkers($lower)) {
            return true;
        }

        if ($this->looksLikePerformerBrigadeSize($lower)) {
            return true;
        }

        $patterns = [
            '/\b(?:ищу|ишу|ищем)\s+(?:работу|подработку|шабашку|объем|объём|роботу|заказ|объект|заказчика)\b/u',
            '/\bв\s+поиске\s+(?:работы|подработки)\b/u',
            '/\b(?:предлага(?:ю|ем)|предоставля(?:ем|ю)|оказыва(?:ем|ю))\s+услуг/u',
            '/\b#?резюме\b/u',
            '/\bменя\s+зовут\b/u',
            '/\bзанимаюсь\b/u',
            '/\bнужна\s+работа\b/u',
            '/\bнужн[аоы]\s+подработк/u',
            '/\bнас\s+дво/u',
            '/\bне\s+даю\b.{0,48}\bподработк/u',
            '/\bкому\s+(?:требуется|нужен|нужна|нужны)\b/u',
            '/\b#(?:ищуработу|помогу|помощник|чертежник|услуга|revit|ревит|bim|фриланс|подработка|работа|удаленн)/ui',
            '/\b(?:revit|bim|autocad|визуализатор|чертежник|архитектор|сметчик).{0,120}\b(?:ищу|предлагаю|резюме|услуг|подработк|сроки)\b/ui',
            '/\b(?:revit|bim)\b/u',
            '/\b(?:сварщик|маляр|электрик|электромонтажник|отделочник|монтажник|штукатур|разнорабоч|сантехник|газорезчик|реставратор|грузчик).{0,96}\b(?:ищ(?:у|ем|ет)|готов(?:ы)?\s+выйти)\b/u',
            '/\bбригад[аы].{0,96}\b(?:предлага(?:ет|ем)|выполн(?:ит|им|ят)|ищем\s+работу|ищет\s+работу|ищет\s+объем|ищет\s+объём|возьм(?:ет|ем|ём)|ищем\s+заказчика|ищем\s+объект)\b/u',
            '/\b(?:готов(?:ы)?\s+(?:выйти|выскочим)|выходим|выйдем)\s+(?:на\s+)?(?:любую\s+)?(?:подработк|работ)/u',
            '/\bработаем\s+сами\b/u',
            '/\bуважаемые\s+заказчик/u',
            '/\b(?:если\s+у\s+вас\s+есть\s+работа|нужны\s+рабочие\s+руки|если\s+вам\s+нужны\s+люди)\b/u',
            '/\bработаем\b.{0,80}\bу\s+кого\s+есть\s+работа\b/u',
            '/\bу\s+кого\s+есть\s+работа\b/u',
            '/\bесть\s+разнорабоч/u',
            '/\b(?:механизированн|механезированн)[а-яё]*\s+штукатур/u',
            '/\b(?:срочно\s+)?нужен\s+человек\s+на\s+объект\b/u',
            '/\bчасто\s+обращаются\s+с\s+запросом\b/u',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * «Бригада 10–15 человек» у исполнителя; у заказчика в том же тексте обычно есть «требу»/«нужн».
     */
    private function looksLikePerformerBrigadeSize(string $lower): bool
    {
        if (preg_match('/\bбригад[аы].{0,96}\d+\s*[-–—]\s*\d+\s*человек/u', $lower) !== 1) {
            return false;
        }

        return preg_match('/\b(?:требу|нужн|ищем\s+людей)/u', $lower) !== 1;
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

        if (preg_match('/\bцена\s+от\s+вас\b/u', $t) === 1) {
            return true;
        }

        if (preg_match('/\bстоимость\s*:/u', $t) === 1) {
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

        // «по 9-10 квадратов», «40 квадратов каждая» — объём без м² в явном виде
        if (preg_match('/\d+\s*[-–]\s*\d+\s+квадрат/u', $t) === 1) {
            return true;
        }

        if (preg_match('/\b\d+\s+квадрат(?:ов|а|ы)\b/u', $t) === 1) {
            return true;
        }

        // Типичный заказ отделки: N колонн (леса/архитектурные тела под штукатурку)
        if (preg_match('/\b\d+\s+колонн\b/u', $t) === 1 || preg_match('/\bесть\s+\d+\s+колонн\b/u', $t) === 1) {
            return true;
        }

        if (preg_match('/\bвысот[аеы]\s+\d+\s*метр/u', $t) === 1) {
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
        if ($this->matchesCommaSeparatedLocationLine($lower)) {
            return true;
        }

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

        if (preg_match('/\b(?:выходить|выйти)\s+нужно.{0,32}завтра/u', $lower) === 1) {
            return true;
        }

        if ($this->matchesSingleWordNonJobLocationLine($lower)) {
            return true;
        }

        return false;
    }

    /**
     * Шапка «Санкт-Петербург, Сестрорецк» в начале (одна строка или с продолжением текста через пробел).
     */
    private function matchesCommaSeparatedLocationLine(string $lower): bool
    {
        $m = [];
        if (preg_match('/^([\p{Cyrillic}\s\-]{5,52}),\s*([\p{Cyrillic}\-]{4,38})\s+/u', $lower, $m) !== 1) {
            if (preg_match('/^([\p{Cyrillic}\s\-]{5,52}),\s*([\p{Cyrillic}\-]{4,38})$/u', $lower, $m) !== 1) {
                $m = [];
                foreach (preg_split("/\r\n|\n|\r/", $lower) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (preg_match('/^([\p{Cyrillic}\s\-]{5,52}),\s*([\p{Cyrillic}\-]{4,38})$/u', $line, $m) === 1) {
                        break;
                    }
                }
            }
        }

        if ($m === []) {
            return false;
        }

        $a = trim($m[1]);
        $b = trim($m[2]);

        if (preg_match('/\d/u', $a) || preg_match('/\d/u', $b)) {
            return false;
        }

        if (preg_match(
            '/делаем|выполня|оказыва|ищем\s+заказ|ищу\s+заказ|бригада|звоните|пишите|мастер/u',
            $a
        )) {
            return false;
        }

        if (preg_match(
            '/цена|руб|₽|р\\/|м²|м2|кв\\.?\\s*м|работ|шпакл|покрас|звон|пишит|телефон|оплат|слои?|слоя/u',
            $a
        )) {
            return false;
        }

        $bNorm = mb_strtolower($b);
        if (in_array($bNorm, ['привет', 'коллеги', 'друзья', 'всем', 'добрый', 'день', 'цена', 'оплата'], true)) {
            return false;
        }

        if (! preg_match('/^[\p{Cyrillic}\s\-]+$/u', $a) || ! preg_match('/^[\p{Cyrillic}\-]+$/u', $b)) {
            return false;
        }

        return str_contains($a, '-') || str_contains($a, ' ') || mb_strlen($a) >= 5;
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
                '/работ|штукатур|маляр|потол|покрас|срочно|требу|нужн|заделка|слой|шпакл|грунт|шлифов|'.
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

        $order = '/(?:^|[\s,.;:!?—\-])(?:нужно|надо|срочно\s+нужно)\s+(?:под\s+)?(?:смонтировать|установить|сделать|снять|демонтировать|провести|починить|построить|заменить|произвести|выполнить|исправить|подготовить)\b/u';
        if (preg_match($order, $lower) === 1) {
            return true;
        }

        return preg_match(
            '/\b(?:нужно|надо|срочно\s+нужно).{0,56}(?:исправить|смонтировать|установить|сделать|выполнить|произвести)\b/u',
            $lower
        ) === 1;
    }

    /**
     * Строка заказа без «мы делаем»: «Укладка газоблока … 600 м² … 650 ₽/м²».
     */
    private function matchesCompactUnitPriceWorkOrder(string $original, string $lower): bool
    {
        if (mb_strlen($original) > 220) {
            return false;
        }

        if ($this->hasPerformerOfferMarkers($lower) || $this->looksLikePerformerOfferOrJobSeeker($lower)) {
            return false;
        }

        return preg_match(
            '/\b(?:укладк|монтаж|демонтаж|штукатур|покрас|кладк|отделк|шпаклев|шпатлев|гипсокартон|газоблок|пеноблок|плитк)'.
            '.{0,96}\d+(?:[\s,.]\d+)?\s*(?:м²|м2|м\s*2|кв\.?\s*м)'.
            '.{0,72}(?:₽|руб\.?|\d+\s*р\.?)'.
            '.{0,24}(?:за\s*)?(?:м²|м2|м\s*2|кв\.?\s*м)\b/us',
            $lower
        ) === 1;
    }

    private function hasPerformerOfferMarkers(string $lower): bool
    {
        $markers = [
            '/\bвыполня(?:ю|им|ем|ят)\b/u',
            '/\bвыполн(?:им|ят|ю)\b/u',
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
            '/\bвозьмём\s+объ/u',
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
