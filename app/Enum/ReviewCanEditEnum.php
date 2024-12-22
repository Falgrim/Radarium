<?php

namespace App\Enum;

enum ReviewCanEditEnum:int {
    case Disabled = 0;

    case Allow = 1;

    public function toString(): ?string
    {
        return match ($this) {
            self::Allow    => 'Разрешено',
            self::Disabled  => 'Запрещено',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Allow    => 'info',
            self::Disabled  => 'yellow',
        };
    }
}
