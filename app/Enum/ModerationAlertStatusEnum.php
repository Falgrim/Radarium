<?php

namespace App\Enum;

enum ModerationAlertStatusEnum:int {
    case Done = 1;
    case Rejected = 2;
    case New = 0;

    public function toString(): ?string
    {
        return match ($this) {
            self::Rejected => 'Отклонено',
            self::Done => 'Закрыто',
            self::New => 'Новое',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Rejected => 'yellow',
            self::Done => 'success',
            self::New => 'info',
        };
    }
}
