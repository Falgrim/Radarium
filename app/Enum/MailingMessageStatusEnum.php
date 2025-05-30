<?php

namespace App\Enum;

enum MailingMessageStatusEnum:string {
    case ToSend = 'to_send';

    case Sended = 'sended';

    case Canceled = 'canceled';

    case Error = 'error';

    public function toString(): ?string
    {
        return match ($this) {
            self::ToSend => 'К отправке',
            self::Sended => 'Отправлено',
            self::Canceled => 'Отменено',
            self::Error => 'Ошибка',
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
            self::Sended => 'success',
            self::Canceled => 'info',
            self::Error => 'danger',
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
