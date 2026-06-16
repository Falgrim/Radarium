<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CatalogPublicationBuilderNonServiceSignals;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CatalogPublicationBuilderNonServiceSignalsTest extends TestCase
{
    private CatalogPublicationBuilderNonServiceSignals $signals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signals = new CatalogPublicationBuilderNonServiceSignals;
    }

    #[DataProvider('performerOfferOrJobSeekerSamples')]
    public function test_performer_offer_or_job_seeker_is_not_hiring(string $text): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertNotContains(
            CatalogPublicationBuilderNonServiceSignals::REASON_HIRING,
            $reasons,
            'Не должно считаться наймом: '.$text
        );
    }

    #[DataProvider('customerHiringSamples')]
    public function test_customer_hiring_still_detected(string $text): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertContains(
            CatalogPublicationBuilderNonServiceSignals::REASON_HIRING,
            $reasons,
            'Должно считаться наймом: '.$text
        );
    }

    #[DataProvider('spamOrLowLexicalSamples')]
    public function test_spam_or_low_lexical_detected(string $text): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertContains(
            CatalogPublicationBuilderNonServiceSignals::REASON_SPAM_OR_LOW_LEXICAL_SIGNAL,
            $reasons,
            'Должно считаться спамом/низким сигналом: '.$text
        );
    }

    #[DataProvider('performerOfferOrJobSeekerSamples')]
    public function test_performer_samples_not_flagged_as_spam(string $text): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertNotContains(
            CatalogPublicationBuilderNonServiceSignals::REASON_SPAM_OR_LOW_LEXICAL_SIGNAL,
            $reasons,
            'Ложное срабатывание спама на примере исполнителя: '.mb_substr($text, 0, 80)
        );
    }

    #[DataProvider('nonFieldConstructionSamples')]
    public function test_should_redirect_to_specialist(string $text): void
    {
        $this->assertTrue(
            $this->signals->shouldRedirectToSpecialist($text),
            'Должно перенаправляться в проектировщики: '.mb_substr($text, 0, 80)
        );
    }

    #[DataProvider('nonFieldConstructionSamples')]
    public function test_non_field_construction_service_detected(string $text): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertContains(
            CatalogPublicationBuilderNonServiceSignals::REASON_NON_FIELD_CONSTRUCTION,
            $reasons,
            'Должно считаться нецелевой услугой (проектирование/дизайн/визуализация): '.mb_substr($text, 0, 80)
        );
    }

    #[DataProvider('fieldConstructionPerformerSamples')]
    public function test_field_construction_performers_not_flagged_as_non_field(string $text): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertNotContains(
            CatalogPublicationBuilderNonServiceSignals::REASON_NON_FIELD_CONSTRUCTION,
            $reasons,
            'Ложное срабатывание на полевого исполнителя: '.mb_substr($text, 0, 80)
        );
    }

    public static function nonFieldConstructionSamples(): array
    {
        return [
            'ux_ui_designer' => [<<<'TXT'
Создам сайт для вашего бизнеса или каталога услуг
Я UX/UI дизайнер сайтов.
Создаю лендинги, многостраничные сайты, интернет-магазины.
Могу разработать только дизайн или помимо дизайна сверстать сайт на Тап Топ/Тильде.
https://www.behance.net/gallery/238731201/Online-store
TXT],
            'visualizer' => [<<<'TXT'
#визуализатор #услуга
Привет! Я предоставляю услуги 3D визуализатора
Цена: 7$ м2
4 ракурса на помещение, 2 включенных круга правок
3ds max, corona
TXT],
            'revit_drafter' => [<<<'TXT'
#услуга #рабочаядокументация #чертежник #Revit
Добрый день.
Разрабатываю рабочую документацию дизайн-проектов. Работаю в ревите, имеется свой шаблон.
Цена: 600 руб. м²
TXT],
            'project_team' => [<<<'TXT'
Опытная команда проектировщиков выполнит:
- Разработка низкополигональных и высокополигональных моделей по требованиям МКА.
- IFC для АГР
- Раздел ГП, АР, КР, КЖ, КМ, ЭС, СС, ОВ, ТС, ВК, НВ и т.д.
- 3D визуализация.
- 360° панорама.
TXT],
            'freelance offer' => ['#подработка #работа #фриланс #Revit #удаленнаяработа Здравствуйте Предлагаю услуги выполнения чертежей'],
            'remote service' => ['#услуга #удаленно #Revit #дизайнпроект #чертежник Добрый день! Меня зовут Анастасия'],
            'drafter help hashtag' => ['#чертежник #помогу Добрый день! меня зовут Дина Лазарева, закончила МГСУ по направлению'],
            'bim developer pitch' => ['#revit #BIM #BIM-разработчик Доброго времени суток коллеги! У всех нас бывают сжатые сроки'],
        ];
    }

    public static function fieldConstructionPerformerSamples(): array
    {
        return [
            'finisher seeking gig' => ['Отделочник ищет подработку, пишите в лс'],
            'brigade offers ceilings' => ['Бригада отделочников от 5-25 человек(РФ) предлагает монтаж: подвесных потолков Armstrong'],
            'take finishing volume' => ['Возьмём объёмы по внутренней отделке помещений, бригада мастеров(2-10 человек) РФ с большим опытом'],
            'electricians seek orders' => ['Бригада электромонтажников, ищем работу. Казань, Республика Татарстан, Москва. Ищем заказы'],
        ];
    }

    public static function spamOrLowLexicalSamples(): array
    {
        $emojiWall = str_repeat('🤍', 18).' '.str_repeat('💫', 12).' https://t.me/stroitel_arhitector';

        return [
            'emoji_wall_with_link' => [$emojiWall],
            'latin_keyboard_mash' => ['mghtuyguuiiu iuhuklikklkjjkyh hiuhoiho kiioioo gfhjgdfjdfgjhd df dgj dgh jgd jg jgdjdghf jdgfh jdgf j'],
        ];
    }

    public static function performerOfferOrJobSeekerSamples(): array
    {
        return [
            'bim resume' => ['#ищуработу Добрый день ищу работу Bim-manager или подработку перевод в 3д revit моё портф'],
            'student resume' => ['#удаленно #резюме #услуга #самозанятость Добрый день! Меня зовут Алексей. Я студент архитектуры'],
            'revit subcontract' => ['#резюме #удаленно Добрый день! Меня зовут Эдуард. Работаю в Revit 23. Ищу подработку по сопровождению'],
            'finisher seeking gig' => ['Отделочник ищет подработку, пишите в лс'],
            'daily pay seeker' => ['День добрый всем. Ищу подработку с ежедневной оплатой. С инструментом знаком. Есть опыт'],
            'electrician seeking work' => ['Добрый день, ищу работу,подработку электрик, оплата ежедневно.89771978062.'],
            'brigade offers ceilings' => ['Бригада отделочников от 5-25 человек(РФ) предлагает монтаж: подвесных потолков Armstrong'],
            'crew offers labor' => ['⭐️Мы предлагаем услуги разнорабочих для работы на различных объектах. -РАЗНОРАБОЧИЕ -МАЛЯРЫ'],
            'crew seeks customer' => ['предоставляем услуги (ищем работу) в Москве. В команде: мужчины до 50–100 человек'],
            'take finishing volume' => ['Возьмём объёмы по внутренней отделке помещений, бригада мастеров(2-10 человек) РФ с большим опытом'],
            'snow removal pitch' => ['❄️ Уборка снега в Москве ❄️ Уважаемые заказчики! Если у вас есть работа по очистке снега — пишите'],
            'electricians seek orders' => ['Бригада электромонтажников, ищем работу. Казань, Республика Татарстан, Москва. Ищем заказы'],
            'night crew seeks employer' => ['Ночь есть 6 человек разнорабочий работаем только с ежедневной оплатой у кого есть работа напишите'],
            'decorative plaster' => ['Добрый день! Меня зовут Роман. Занимаюсь нанесением декоративной штукатурки любой сложности'],
            'restoration crew' => ['Здравствуйте. Возьмём объём работ по реставрации. В команде: -лепщики реставраторы гипсовые формы'],
            'pair seeking gig' => ['Нас двое,гражданство РФ,очень нужна подработка на завтра,руки растут откуда надо,умеем много'],
            'laborers available' => ['Есть разнорабочие мужчины: демонтаж Подъем материала Уборка территории Помощь мастеру'],
            'ac installer pitch' => ['Кому требуется мастер по монтажу кондицианеров пишите!'],
            'urgent worker pitch' => ['Коллеги, если вдруг срочно нужен человек на объект и нет времени на долгие поиски — напишите'],
            'seeking daily pay job' => ['Нужна работа с ежедневной оплатой От 5т 2-3 человека будут'],
            'small brigade availability' => ['Добрый вечер. Бригада монтажников/разнорабочих 4-5 человек. Русские, на авто,инструмент свой'],
            'brigades will do any work' => ['Бригады выполнят любые виды строительных работ,есть организация работает по ИП или наличные'],
            'movers crew' => ['ГPУЗЧИKИ/ПОДСOБНЫE/XЕЛПЕРЫ Оперативная Бригада приедет быстро в срок. Выполнит любые физические работы'],
            'mechanized plaster crews' => ['Механезированная штукатурка стен. Семь бригад с большим опытом работы. Бригады по 6- 8 человек'],
            'restoration offer' => ['Здравствуйте! Выполним работу по реставрации/новоделу декора: - лепка -штукатурные тяги -м'],
            'our brigade headcount offer' => ['Наша бригада из 5 человек делаем кладку газоблока, звоните, работаем по договору'],
        ];
    }

    public static function customerHiringSamples(): array
    {
        return [
            'mass hire' => ['Требуются: Моляры. Бригада 10-15 человек. Аванс на 3й день.'],
            'helper murino' => ['Требуется помощник - строитель (самостоятельный и без выхлопа) Оплата из расчета 500 руб./час. Работа по 4 часа 3 раза в неделю.'],
            'gig slot' => ['Есть подработка Кому интересно пишите в лс! Все детали расскажу'],
            'need crew tomorrow' => ['Нужна на завтра подработка от 4000'],
            'need drywall crew' => ['Нужна бригада , для возведения потолка и перегородок из ГКЛ. Проект есть , для ознакомления'],
            'need helper on site' => ['Нужен помощник на строительный объект в Лосино-Петровском. 4000₽ за 10 часовую смену.'],
            'demolition telegram' => ['Еще одного С завтрашнего дня К 9:00 2 человека (трезвые , РФ) Каменный остров Сбивать штукатурку перфоратором https://t.me/mobilepersonnel'],
            'need one person digit' => [
                'Всем добрый вечер. Мастер универсал с умением делать сантехнику, электрику. Нужен 1 человек, бригадам звонить смысла нет. Выходить можно завтра!',
            ],
            'tilesetter emoji header' => [
                "🍫Требуется плиточник!\n1. Устройство Плитки пол 20*120 20м2\n2. Устройство Плитки стен 60*60 , 120*20 25м2\n₽ 100т.\n📍Москва, Троицкий административный округ.",
            ],
            'subcontractor tender ip only' => [
                "Требуется подрядчик на ремонт кровли\n82 000 700\nАванс 30%\nТОЛЬКО ИП И ООО\nЧАСТНЫХ ЛИЦ ПРОСЬБА НЕ БЕСПОКОИТЬ",
            ],
            'who does facade volume brigade' => [
                'Кто занимается фасадом.Есть хорооиц объем,нормальные цены.Желательно сразу бригада из 8 человек.С жильём решим.',
            ],
            'masons day pay piecework' => [
                "КАМЕНЩИКИ\nГазоблок 75мм-150м 2-700₽\nОблицовочный кирпич-15 м2-2600₽\nЛен.Обл\nРасчет сразу день в день за проделанный кусок работы",
            ],
        ];
    }

    #[DataProvider('customerCompactOrderSamples')]
    public function test_customer_compact_orders_detected(string $text, string $expectedReason): void
    {
        $reasons = $this->signals->reasons($text);

        $this->assertContains($expectedReason, $reasons, 'Ожидался сигнал '.$expectedReason);
    }

    public static function customerCompactOrderSamples(): array
    {
        return [
            'malyar_fix_layers' => [
                <<<'TXT'
12.05.2026
Всем добрый вечер.
!!СРОЧНО!!
Мастер маляр на шпатлевку
Нужно под покраску исправить два финишных слоя, объем 100м2.
Стоимость: так как переделка, понимаю что это сложнее, цена от вас!
Фото по запросу!
Выходить нужно завтра!
89167258199 - Леонид
TXT,
                CatalogPublicationBuilderNonServiceSignals::REASON_CUSTOMER_SHORT,
            ],
            'gazoblock_unit_price' => [
                <<<'TXT'
14.03.2026
Укладка Газоблока 75 мм
600 м 2 -650 ₽ за м 2
TXT,
                CatalogPublicationBuilderNonServiceSignals::REASON_COMPACT_UNIT_PRICE_WORK_ORDER,
            ],
        ];
    }
}
