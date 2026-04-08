<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s1 = 'Для обсуждения деталей пишите в личные сообщения Мах +79026395775 или звоните';
$s2 = "Контакты:\ntg: Zhenyabaikall\n";
$s3 = 'Мах &#43;79026395775 звоните';

foreach (['s1' => $s1, 's2' => $s2, 's3' => $s3] as $k => $s) {
    $html = \App\Models\ApiPostUser::prepareLastPostText($s, false)->toHtml();
    echo "=== $k ===\n$html\n\n";
}
