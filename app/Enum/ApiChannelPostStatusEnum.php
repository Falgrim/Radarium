<?php

namespace App\Enum;

enum ApiChannelPostStatusEnum:int {
    case Complete = 1;

    case InQueue = 0;

    case Error = 2;

    public function toString(): ?string
    {
        return match ($this) {
            self::Complete  => 'Обработано',
            self::InQueue   => 'В очереди',
            self::Error     => 'Ошибка обработки',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Complete  => 'success',
            self::InQueue   => 'yellow',
            self::Error     => 'danger',
        };
    }
}
