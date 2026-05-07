<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BuilderVacancyGigHeuristic;
use PHPUnit\Framework\TestCase;

final class BuilderVacancyGigHeuristicTest extends TestCase
{
    public function test_detects_logistics_shift_post_as_vacancy(): void
    {
        $text = <<<'TXT'
Завтра К 8:00 М. Ломоносовская (автобус 119, 476) М.Дыбенко (автобус 4, 285)
Октябрьская наб., д.102 к 2 лит. О Стройка, уборка, наведение порядка, принести, унести и др. подсобные работы
3300₽ (8 часов) С 8:00-16:00(без обеда) Перекусить дадут
Оплата по окончанию на карту (в течении часа)
Кто готов пишем в ЛС возраст, ФИО, номер телефона.
TXT;

        $this->assertTrue(BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text));
    }

    public function test_brigade_service_offer_is_not_vacancy(): void
    {
        $text = 'Мы бригада маляров, ремонт квартир под ключ, опыт 15 лет, звоните, смета бесплатно.';

        $this->assertFalse(BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text));
    }

    public function test_helper_hourly_part_time_is_vacancy_heuristic(): void
    {
        $text = <<<'TXT'
Всем привет.
Требуется помощник - строитель (самостоятельный и без выхлопа)
Оплата из расчета 500 руб./час.
Работа по 4 часа 3 раза в неделю.
TXT;

        $this->assertTrue(BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text));
    }

    public function test_ls_and_two_people_is_vacancy_heuristic(): void
    {
        $text = <<<'TXT'
Еще одного
С завтрашнего дня
К 9:00
2 человека (трезвые , РФ)
Сбивать штукатурку перфоратором
Инструмент предоставляется
Оплата 100р с квадрата
Писать в лс
TXT;

        $this->assertTrue(BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text));
    }

    public function test_housing_tools_biweekly_is_vacancy_heuristic(): void
    {
        $text = <<<'TXT'
Есть проживание, инструмент выдается, аванс на 3 день работы, выплаты два раза в месяц.
Начальный объем 1000м².
TXT;

        $this->assertTrue(BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text));
    }

    public function test_telegram_job_link_with_field_work_is_vacancy(): void
    {
        $text = <<<'TXT'
2 человека
Перфоратор
Писать в лс
https://t.me/mobilepersonnel
TXT;

        $this->assertTrue(BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text));
    }
}
