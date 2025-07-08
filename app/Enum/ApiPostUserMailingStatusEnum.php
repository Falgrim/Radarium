<?php

namespace App\Enum;

enum ApiPostUserMailingStatusEnum:int {
    case ToSend = 2;

    case Disabled = 0;

    case Sended = 1;

    case Error = 3;

    case Waiting = 4;

    public function toString(): ?string
    {
        return match ($this) {
            self::ToSend  => 'Ожидает отправки',
            self::Disabled => 'Отключено',
            self::Sended => 'Отправлено',
            self::Error => 'Ошибка',
            self::Waiting => 'Ожидает проверки ИИ',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::ToSend => 'yellow',
            self::Disabled => 'info',
            self::Sended => 'success',
            self::Error => 'danger',
            self::Waiting => 'info',
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
