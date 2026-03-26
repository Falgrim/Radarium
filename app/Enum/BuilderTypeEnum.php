<?php

namespace App\Enum;

enum BuilderTypeEnum: string
{
    case Service = 'предложение услуги';
    case Vacancy = 'вакансия';
    case Junk    = 'мусор';
}
