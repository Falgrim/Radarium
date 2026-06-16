<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/app/Services/CatalogPublicationBuilderNonServiceSignals.php';

use App\Services\CatalogPublicationBuilderNonServiceSignals;

$s = new CatalogPublicationBuilderNonServiceSignals();

$samples = [
    'ux_ui' => <<<'TXT'
Создам сайт для вашего бизнеса
Я UX/UI дизайнер сайтов.
Сверстаю сайт на Тап Топ/Тильде.
https://www.behance.net/gallery/238731201/Online-store
TXT,
    'visualizer' => <<<'TXT'
#визуализатор #услуга
Привет! Я предоставляю услуги 3D визуализатора
3ds max, corona, 4 ракурса, 2 круга правок
TXT,
    'revit_drafter' => <<<'TXT'
#услуга #чертежник #Revit
Разрабатываю рабочую документацию дизайн-проектов. Работаю в ревите.
TXT,
    'project_team' => <<<'TXT'
Опытная команда проектировщиков выполнит:
IFC для АГР
Раздел ГП, АР, КР, КЖ, КМ, ЭС, СС, ОВ, ТС, ВК, НВ
3D визуализация. 360° панорама.
TXT,
    'field_brigade' => 'Бригада отделочников предлагает монтаж подвесных потолков Armstrong',
];

$expected = CatalogPublicationBuilderNonServiceSignals::REASON_NON_FIELD_CONSTRUCTION;

foreach ($samples as $name => $text) {
    $reasons = $s->reasons($text);
    $blocked = in_array($expected, $reasons, true);
    $ok = ($name === 'field_brigade') ? ! $blocked : $blocked;
    echo ($ok ? 'OK' : 'FAIL')." {$name}: ".(count($reasons) ? implode(', ', $reasons) : '(нет)').PHP_EOL;
}
