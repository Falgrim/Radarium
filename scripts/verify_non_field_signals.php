<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/app/Services/CatalogPublicationBuilderNonServiceSignals.php';

use App\Services\CatalogPublicationBuilderNonServiceSignals;

$s = new CatalogPublicationBuilderNonServiceSignals();

$shouldRedirect = [
    'ux_ui' => 'Создам сайт. UX/UI дизайнер. Тильде behance.net',
    'visualizer' => '#визуализатор #услуга 3D визуализатора 3ds max corona',
    'revit_drafter' => '#услуга #чертежник #Revit Разрабатываю рабочую документацию дизайн-проектов',
    'project_team' => 'команда проектировщиков МКА IFC для АГР Раздел ГП, АР, КР, КЖ, КМ, ЭС, СС, ОВ, ТС, ВК, НВ',
    'architect' => 'Добрый день! Меня зовут Ирина, я выполняю архитектурно-строительные проекты домов',
    'web_tilda' => '#помогу #сайты #тильда #вебдизайнер Добрый день) Меня зовут Ирина. Я занимаюсь веб-дизайном',
];

$shouldStayBuilder = [
    'brigade_20' => '*Москва и Московская область* Бригада рабочих, 20+ человек. Мастера-универсалы. Качественно выполняем отделку',
    'brigade_turnkey' => 'В ПОИСКАХ ТОЛЬКО ОБЪЕКТЫ ПОД КЛЮЧ МЕЛОЧАМИ НЕ ЗАНИМАЕМСЯ. Москва и Московская область Бригада рабочих',
    'smetchik' => '🔥ИНЖЕНЕР СМЕТЧИК Составление СМЕТ КС2 КС3 УСЛУГИ СМЕТЧИКА КОНЪЮНКТУРНЫЙ АНАЛИЗ',
    'smet_sections' => 'УСЛУГИ СМЕТЧИКА Составляем Сметы, КС-2, КС-3, АР, КЖ, КР, КМ, ВК, ОВ, СС, ЭС',
    'electric' => 'Сантехнические /электромонтажные работы на профессиональном уровне! комплексный монтаж',
    'foundation' => 'Фундамент. Фундаменты различных форм: Ленточный фундамент Плитный фундамент',
    'facade' => 'Устройство фасада, ремонт фасада, отделка фасада, фасадные работы',
    'repair_design' => 'Выполняем дизайн-проекты и ремонт квартир. Опытные Русские мастера. Гарантия.',
    'ppr_pto' => 'САМОЗАНЯТЫЙ СПЕЦИАЛИСТ ПТО Оказываю услуги: Разработка проектов производства работ ППР',
    'survey' => 'Предлагаю услуги: Обследование зданий и сооружений Строительная экспертиза',
    'metal' => 'Изготовление металлоконструкций и изделий Сотрудничаем с подрядчиками',
    'turnkey_repair' => 'Готовы рассмотреть ваши предложения по ремонту объектов ПОД КЛЮЧ. Москва и Московская область Бригада',
    'malyar_design' => 'Малярно отделочный мастер. От простой косметики до дизайн проекта',
    'print_design' => 'Занимаюсь распечаткой/брошюровкой Дизайн-проектов. Доставка по Москве',
];

$reason = CatalogPublicationBuilderNonServiceSignals::REASON_NON_FIELD_CONSTRUCTION;

$fail = 0;

foreach ($shouldRedirect as $name => $text) {
    $blocked = in_array($reason, $s->reasons($text), true);
    $ok = $blocked;
    echo ($ok ? 'OK' : 'FAIL')." redirect {$name}\n";
    if (! $ok) {
        $fail++;
    }
}

foreach ($shouldStayBuilder as $name => $text) {
    $blocked = in_array($reason, $s->reasons($text), true);
    $ok = ! $blocked;
    echo ($ok ? 'OK' : 'FAIL')." stay_builder {$name}".($blocked ? ' (matched!)' : '')."\n";
    if (! $ok) {
        $fail++;
    }
}

echo $fail === 0 ? "\nALL OK\n" : "\nFAILURES: {$fail}\n";
