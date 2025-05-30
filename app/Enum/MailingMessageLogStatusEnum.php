<?php

namespace App\Enum;

enum MailingMessageLogStatusEnum:string {
    case Error = 'error';

    case Success = 'success';

    case Unknown = 'unknown';

    public function toString(): ?string
    {
        return match ($this) {
            self::Error    => 'Ошибка',
            self::Success  => 'Доставлено',
            self::Unknown  => 'Неизвестно',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Error    => 'danger',
            self::Success  => 'success',
            self::Unknown  => 'yellow',
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
