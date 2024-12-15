<?php

namespace App\Enum;

enum ApiAiSourceEnum:string {
    case YandexGTP4 = 'yandexgtp4';

    public function toString(): ?string
    {
        return match ($this) {
            self::YandexGTP4    => 'Яндекс GPT 4',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::YandexGTP4    => 'success',
        };
    }
}
