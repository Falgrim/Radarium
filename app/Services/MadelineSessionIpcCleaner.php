<?php

namespace App\Services;

class MadelineSessionIpcCleaner
{
    /**
     * Сброс stale IPC MadelineProto (docs/RADARIUM_TECHDOC.md §6.9, docs/runbooks/madelineproto-vpn-routing.md).
     * Нужен после переавторизации и при «The endpoint does not exist!».
     */
    public static function clear(string $sessionName): void
    {
        $sessionDir = base_path($sessionName);
        if (!is_dir($sessionDir)) {
            return;
        }

        $ipcState = $sessionDir . DIRECTORY_SEPARATOR . 'ipcState.php';
        if (is_file($ipcState)) {
            @unlink($ipcState);
        }

        foreach (['ipc', 'callback.ipc'] as $entry) {
            $path = $sessionDir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                self::removeDirectory($path);
            }
        }
    }

    private static function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
