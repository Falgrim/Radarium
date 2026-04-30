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
}
