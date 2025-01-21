<?php

namespace App\Enum;

enum ReviewStatusEnum:int {
    case Active = 2;
    case Disabled = 0;

    case InModeration = 1;

    public function toString(): ?string
    {
        return match ($this) {
            self::Active    => 'Активно',
            self::Disabled  => 'Отклонено',
            self::InModeration  => 'Ожидает модерации',
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
            self::Disabled  => 'red',
            self::InModeration  => 'info',
        };
    }
}
