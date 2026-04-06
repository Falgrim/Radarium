<?php

declare(strict_types=1);

namespace App\Enum;

enum AdminSystemPromptScope: string
{
    case Builder = 'builder';

    case Specialist = 'specialist';
}
