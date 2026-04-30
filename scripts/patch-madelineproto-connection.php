<?php

declare(strict_types=1);

/**
 * MadelineProto 8.x: Connection::setExtra содержит строку
 * `$this->API->logger = $this->API->logger;`, которая на PHP 8.3+ падает с
 * "Typed property danog\MadelineProto\MTProto::$logger must not be accessed before initialization".
 * Удаляем её после каждого `composer install` (vendor не в репозитории).
 */
$root = dirname(__DIR__);
$file = $root . '/vendor/danog/madelineproto/src/Connection.php';

if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "patch-madelineproto-connection: skip, {$file} not found\n");
    exit(0);
}

$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "patch-madelineproto-connection: cannot read {$file}\n");
    exit(1);
}

$pattern = '/^[ \t]*\$this->API->logger = \$this->API->logger;\s*\R/m';
$newContent = preg_replace($pattern, '', $content, 1);

if ($newContent === null) {
    fwrite(STDERR, "patch-madelineproto-connection: preg_replace failed\n");
    exit(1);
}

if ($newContent === $content) {
    exit(0);
}

if (file_put_contents($file, $newContent) === false) {
    fwrite(STDERR, "patch-madelineproto-connection: cannot write {$file}\n");
    exit(1);
}

fwrite(STDOUT, "patch-madelineproto-connection: patched Connection::setExtra in danog/madelineproto\n");
