<?php

namespace App\Enum;

enum ApiChannelSourceEnum:string {
    //case VK = 'vk';
    case Telegram = 'telegram';

    public function toString(): ?string
    {
        return match ($this) {
            //self::VK        => 'ВКонтакнте',
            self::Telegram  => 'Telegram',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            //self::VK        => 'info',
            self::Telegram  => 'info',
        };
    }
}
