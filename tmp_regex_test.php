<?php

$s1 = 'Для обсуждения деталей пишите в личные сообщения Мах +79026395775 или звоните';
$s2 = "Контакты:\ntg: Zhenyabaikall\n";

$p = '/(?:\+7|8|7)(?:[\s\p{Zs}()._-]*\d){10}(?!\d)/u';
preg_match_all($p, $s1, $m, PREG_OFFSET_CAPTURE);
echo "flex on s1:\n";
var_export($m[0]);
echo "\n\n";

// bytes around +
$pos = strpos($s1, '79026395775');
echo "pos 790...: $pos\n";
if ($pos > 0) {
    for ($i = $pos - 3; $i < $pos + 15 && $i < strlen($s1); $i++) {
        $b = $s1[$i];
        echo "[$i] ord=" . ord($b) . " char=" . ($b === '+' ? 'PLUS' : $b) . "\n";
    }
}

// Unicode plus U+FF0B fullwidth
$s3 = 'Мах ＋79026395775 или'; // fullwidth plus
preg_match_all($p, $s3, $m3, PREG_OFFSET_CAPTURE);
echo "\nflex on fullwidth plus string:\n";
var_export($m3[0]);
echo "\n";

$p2 = '/(?:\+7|8|7)[\s\p{Zs}()._-]*\d{3}[\s\p{Zs}()._-]*\d{3}[\s\p{Zs}()._-]*\d{2}[\s\p{Zs}()._-]*\d{2}(?!\d)/u';
preg_match_all($p2, $s1, $m2, PREG_OFFSET_CAPTURE);
echo "\nclassic on s1:\n";
var_export($m2[0]);
echo "\n";
