<?php

namespace App\Enum;

enum IsCompanyEnum:int {
    case Company = 1;

    case Private = 0;

    public function toString(): ?string
    {
        return match ($this) {
            self::Company   => 'Вакансия',
            self::Private   => 'Сотрудник',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Company   => 'info',
            self::Private   => 'yellow',
        };
    }
}
