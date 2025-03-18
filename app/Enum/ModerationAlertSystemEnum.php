<?php

namespace App\Enum;

enum ModerationAlertSystemEnum:int {
    case System = 1;
    case User = 0;

    public function toString(): ?string
    {
        return match ($this) {
            self::System => 'Система',
            self::User => 'Пользователь',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::System => 'yellow',
            self::User => 'info',
        };
    }
}
