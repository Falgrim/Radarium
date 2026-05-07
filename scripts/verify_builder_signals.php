<?php

/**
 * Быстрая проверка эвристик без composer (только этот файл + класс).
 * Запуск: php scripts/verify_builder_signals.php
 */

declare(strict_types=1);

require_once dirname(__DIR__).'/app/Services/CatalogPublicationBuilderNonServiceSignals.php';
require_once dirname(__DIR__).'/app/Services/BuilderVacancyGigHeuristic.php';

use App\Services\BuilderVacancyGigHeuristic;
use App\Services\CatalogPublicationBuilderNonServiceSignals;

$svc = new CatalogPublicationBuilderNonServiceSignals;

$samples = [
    'hiring_помощник' => 'Требуется помощник - строитель. Оплата из расчета 500 руб./час. Работа по 4 часа 3 раза в неделю.',
    'gig_bundle_потолок' => "1200р/м²\nшпаклевка\nОбъем 800м²\nСестрорецк\nОПЛАТА ПО ФАКТУ СДЕЛАННОГО",
    'service_без_места' => 'Делаем отделку, цена 900 руб/м², типовые объекты от 50 м², качество, звоните.',
    'заказчик_укладка' => 'Добрый день! Нужно произвести укладку плитки, 10м2, оплата 10000р',
    'найм_сочи' => "❗Срочно ❗\nг Сочи 📍\nТребуются:\nМоляры\nБригада 10-15 человек\n1100 за м²\n3000 м²",
];

echo "=== CatalogPublicationBuilderNonServiceSignals::reasons() ===\n";
foreach ($samples as $name => $text) {
    $reasons = $svc->reasons($text);
    echo $name.': '.(count($reasons) ? implode(', ', $reasons) : '(нет сигналов)').PHP_EOL;
}

$vacancySamples = [
    'помощник_график' => $samples['hiring_помощник'],
    'бригада_маляров' => 'Мы бригада маляров, ремонт квартир под ключ, опыт 15 лет, звоните.',
];

echo "\n=== BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy() ===\n";
foreach ($vacancySamples as $name => $text) {
    $v = BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text) ? 'да (как вакансия)' : 'нет';
    echo $name.': '.$v.PHP_EOL;
}

$userSamples = [
    'u1_фасад_долгопрудный' => <<<'TXT'
м.Долгопрудная
Начальный обьем 1000м².
Есть проживание, инструмент выдается, аванс на 3 день работы, выплаты два раза в месяц
TXT
    ,
    'u2_демонтаж_tme' => <<<'TXT'
Еще одного
2 человека (трезвые , РФ)
Каменный остров
Сбивать штукатурку перфоратором до кирпича
Инструмент предоставляется
Оплата 100р с квадрата
Писать в лс

https://t.me/mobilepersonnel
TXT
    ,
];

echo "\n=== Примеры из тикета ===\n";
foreach ($userSamples as $name => $text) {
    $reasons = $svc->reasons($text);
    echo $name.': '.(count($reasons) ? implode(', ', $reasons) : '(нет сигналов)').PHP_EOL;
    $v = BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($text) ? 'да' : 'нет';
    echo '  vacancy override: '.$v.PHP_EOL;
}

echo PHP_EOL.'OK'.PHP_EOL;
