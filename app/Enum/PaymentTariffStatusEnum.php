<?php

namespace App\Enum;

enum PaymentTariffStatusEnum:string {
    case Active = 'active';

    case Disabled = 'disabled';

    case Draft = 'draft';

    case Archive = 'archive';

    public function toString(): ?string
    {
        return match ($this) {
            self::Active => 'Активно',
            self::Disabled => 'Отключено',
            self::Draft => 'Черновик',
            self::Archive => 'Архив',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Active => 'success',
            self::Disabled => 'yellow',
            self::Draft => 'gray',
            self::Archive => 'info',
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
