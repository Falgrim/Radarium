<?php

namespace App\Enum;

enum ModerationAlertTableNameEnum:string {
    case Author = 'ApiPostUser';
    case Specialist = 'Specialist';
    case Builder = 'Builder';
    case CompanyJob = 'CompanyJob';
    case ReviewSpecialist = 'Review';
    case ReviewBuilder = 'BuilderReview';
    case ReviewCompanyJob = 'CompanyJobReview';

    public function toString(): ?string
    {
        return match ($this) {
            self::Author => 'Специалист.',
            self::Specialist => 'Проектирование.',
            self::Builder => 'Строительство.',
            self::CompanyJob => 'Вакансии.',
            self::ReviewSpecialist => 'Проектирование. Отзыв.',
            self::ReviewBuilder => 'Строительство. Отзыв.',
            self::ReviewCompanyJob => 'Вакансии. Отзыв.',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Author => 'gray',
            self::Specialist => 'yellow',
            self::Builder => 'purple',
            self::CompanyJob => 'info',
            self::ReviewSpecialist => 'yellow',
            self::ReviewBuilder => 'purple',
            self::ReviewCompanyJob => 'info',
        };
    }
}
