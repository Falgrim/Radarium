<?php

declare(strict_types=1);

namespace App\Enum;

enum ActiveAuthorsReportTypeEnum: string
{
    case Builder = 'builder';

    case Specialist = 'specialist';

    case CompanyJob = 'company_job';

    public function label(): string
    {
        return match ($this) {
            self::Builder => 'Строительство',
            self::Specialist => 'Проектирование',
            self::CompanyJob => 'Вакансии',
        };
    }

    public static function tryFromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Builder;
    }
}
