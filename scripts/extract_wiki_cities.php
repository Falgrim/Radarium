<?php

$src = $argv[1] ?? __DIR__ . '/../storage/app/wiki_cities.md';
if (! is_readable($src)) {
    fwrite(STDERR, "Usage: php scripts/extract_wiki_cities.php <path-to-wiki-markdown>\n");

    exit(1);
}

$f = file_get_contents($src);
preg_match_all('/\|\s*\d+\s*\|\s*(?:\d+\s*\|\s*)?\[([^\]]+)\]\(/u', $f, $m);
$cities = array_values(array_unique($m[1]));
sort($cities, SORT_STRING);

$out = __DIR__ . '/../app/Data/russian_cities_100k.json';
file_put_contents($out, json_encode($cities, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($cities) . ' cities -> ' . $out . PHP_EOL;
