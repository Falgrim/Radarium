<?php

namespace App\Enum;

enum ApiAiListStatusEnum:int {
    case Active = 1;
    case Disabled = 0;

    public function toString(): ?string
    {
        return match ($this) {
            self::Active    => 'Активно',
            self::Disabled  => 'Отключено',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Active    => 'success',
            self::Disabled  => 'yellow',
        };
    }
}
