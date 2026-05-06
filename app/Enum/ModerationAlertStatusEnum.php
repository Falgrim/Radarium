<?php

namespace App\Enum;

enum ModerationAlertStatusEnum:int {
    case New = 0;

    case Done = 1;

    case Rejected = 2;

    /** После «Повторная ИИ обработка»: карточки сняты, пост в очереди ИИ. */
    case AiReprocessing = 3;

    /** После «Убрать из каталога»: публикация снята без очереди ИИ. */
    case RemovedFromCatalog = 4;

    public function toString(): ?string
    {
        return match ($this) {
            self::New => 'Новое',
            self::Done => 'Закрыто',
            self::Rejected => 'Отклонено',
            self::AiReprocessing => 'Повторная обработка',
            self::RemovedFromCatalog => 'Убрано из каталога',
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
            self::Done => 'success',
            self::Rejected => 'yellow',
            self::AiReprocessing => 'purple',
            self::RemovedFromCatalog => 'yellow',
        };
    }
}
