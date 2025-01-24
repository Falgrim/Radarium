<?php

namespace App\Enum;

enum SpecialistStatusEnum:int {
    case Active = 2;

    case Disabled = 0;

    case Error = 3;

    case InModeration = 1;

    public function toString(): ?string
    {
        return match ($this) {
            self::Active    => 'Активно',
            self::Disabled  => 'Отключено',
            self::InModeration  => 'Ожидает модерации',
            self::Error  => 'Ошибка',
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
            self::Disabled  => 'yellow',
            self::InModeration  => 'info',
            self::Error  => 'danger',
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
