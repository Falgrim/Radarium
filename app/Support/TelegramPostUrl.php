<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Публичная ссылка на сообщение в Telegram по ссылке канала/группы и ID сообщения в API Telegram.
 */
final class TelegramPostUrl
{
    public static function fromChannelLinkAndPostId(?string $channelLink, ?int $telegramPostId): ?string
    {
        if ($channelLink === null || $telegramPostId === null || $telegramPostId <= 0) {
            return null;
        }

        $link = trim($channelLink);
        if ($link === '' || ! preg_match('#^https?://#i', $link)) {
            return null;
        }

        // Приватные invite-ссылки (t.me/+…) не позволяют адресовать конкретный пост.
        if (preg_match('#t\.me/\+#i', $link)) {
            return null;
        }

        return rtrim($link, '/').'/'.$telegramPostId;
    }
}
