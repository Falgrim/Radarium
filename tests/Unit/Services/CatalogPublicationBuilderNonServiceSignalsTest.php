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

    public static function performerOfferOrJobSeekerSamples(): array
    {
        return [
            'bim resume' => ['#ищуработу Добрый день ищу работу Bim-manager или подработку перевод в 3д revit моё портф'],
            'freelance offer' => ['#подработка #работа #фриланс #Revit #удаленнаяработа Здравствуйте Предлагаю услуги выполнения чертежей'],
            'remote service' => ['#услуга #удаленно #Revit #дизайнпроект #чертежник Добрый день! Меня зовут Анастасия'],
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
        ];
    }
}
