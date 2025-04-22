<?php

namespace App\Enum;

enum PaymentServicePayEnum:int {
    case Robokassa = 1;

    public function toString(): ?string
    {
        return match ($this) {
            self::Robokassa => 'Robokassa',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Robokassa => 'info',
        };
    }

    public static function getList(): array
    {
        $values = collect(self::cases());

        $result = $values->mapWithKeys(fn ($value): array => [
            $value->value => method_exists($value, 'toString') ? $value->toString() : $value->value
        ]);
        $result->put('', 'Все статусы');

        return $result->toArray();
    }
}
