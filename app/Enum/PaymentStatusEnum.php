<?php

namespace App\Enum;

enum PaymentStatusEnum:string {
    case New = 'new';

    case TTL = 'ttl';

    case Error = 'error';

    case Success = 'success';

    case Canceled = 'canceled';

    public function toString(): ?string
    {
        return match ($this) {
            self::New => 'Создан',
            self::TTL => 'Отменен по времени',
            self::Error => 'Ошибка',
            self::Success => 'Успешный',
            self::Canceled => 'Отменен',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::New => 'info',
            self::TTL => 'info',
            self::Error => 'danger',
            self::Success => 'success',
            self::Canceled => 'info',
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
