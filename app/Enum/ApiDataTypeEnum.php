<?php

namespace App\Enum;

enum ApiDataTypeEnum:int {
    case Company = 1;

    case Specialist = 0;

    case Builder = 2;

    public function toString(): ?string
    {
        return match ($this) {
            self::Company   => 'Вакансия',
            self::Specialist   => 'Сотрудник',
            self::Builder   => 'Строитель',
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
            self::Specialist   => 'yellow',
            self::Builder   => 'purple',
        };
    }
}
