<?php

declare(strict_types=1);

namespace App\Enum;

enum ParsingTestRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Ожидает запуска',
            self::Running => 'Выполняется',
            self::Completed => 'Завершён',
            self::Failed => 'Ошибка',
            self::Cancelled => 'Прерван',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
