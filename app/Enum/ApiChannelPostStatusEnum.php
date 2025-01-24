<?php

namespace App\Enum;

enum ApiChannelPostStatusEnum:int {
    case Complete = 1;

    case InQueue = 0;

    case Error = 2;

    case Empty = 3;

    case DontMatch = 4;

    public function toString(): ?string
    {
        return match ($this) {
            self::Complete  => 'Обработано',
            self::InQueue   => 'В очереди',
            self::Error     => 'Ошибка обработки',
            self::DontMatch => 'Тип выборки не подходит',
            self::Empty     => 'Нет данных',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Complete  => 'success',
            self::InQueue   => 'yellow',
            self::Error     => 'danger',
            self::DontMatch => 'purple',
            self::Empty     => 'yellow',
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
