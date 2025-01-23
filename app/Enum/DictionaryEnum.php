<?php

namespace App\Enum;

enum DictionaryEnum:string {
    case Speciality = 'specialties';

    public function toString(): ?string
    {
        return match ($this) {
            self::Speciality    => 'Специальности',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }
}
