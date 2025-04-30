<?php

namespace App\Enum;

enum UserTariffStatusEnum:string {
    case Active = 'active';

    case Disabled = 'disabled';

    case Cancel = 'cancel';

    case Ended = 'ended';

    public function toString(): ?string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Disabled => 'Отключен',
            self::Ended => 'Закончился',
            self::Cancel => 'Отменен',
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
            self::Ended => 'yellow',
            self::Cancel => 'gray',
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
