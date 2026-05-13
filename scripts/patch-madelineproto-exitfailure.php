<?php

declare(strict_types=1);

/**
 * MadelineProto 8.x: ExitFailure::getException() через Reflection выставляет свойство
 * `previous` на экземпляре AssertionError. В PHP 8.4+ это даёт deprecation
 * «Creation of dynamic property AssertionError::$previous is deprecated» и может
 * сопровождаться нестабильностью IPC-воркера. Пропускаем установку previous для AssertionError.
 */
$root = dirname(__DIR__);
$file = $root . '/vendor/danog/madelineproto/src/Ipc/ExitFailure.php';

if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "patch-madelineproto-exitfailure: skip, {$file} not found\n");
    exit(0);
}

$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "patch-madelineproto-exitfailure: cannot read {$file}\n");
    exit(1);
}

if (str_contains($content, '$exception instanceof \AssertionError')) {
    exit(0);
}

$needle = '$key = $prop->getName();';
$pos = strpos($content, $needle);
if ($pos === false) {
    fwrite(STDERR, "patch-madelineproto-exitfailure: anchor not found, skip\n");
    exit(0);
}

$block = <<<'PHP'

                if ($key === 'previous' && $exception instanceof \AssertionError) {
                    continue;
                }
PHP;

$before = substr($content, 0, $pos);
$after = substr($content, $pos + strlen($needle));
$newContent = $before . $needle . $block . $after;

if (file_put_contents($file, $newContent) === false) {
    fwrite(STDERR, "patch-madelineproto-exitfailure: cannot write {$file}\n");
    exit(1);
}

fwrite(STDOUT, "patch-madelineproto-exitfailure: patched Ipc/ExitFailure.php\n");
