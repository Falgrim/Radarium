<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiDataTypeEnum;
use App\MoonShine\Resources\SpecialistResource;

final class SpecialistSystemPromptPage extends SystemPromptPage
{
    public function __construct()
    {
        parent::__construct(
            scope: AdminSystemPromptScope::Specialist,
            applyDataType: ApiDataTypeEnum::Specialist,
            sectionResourceClass: SpecialistResource::class,
            sectionMenuLabel: 'Проектирование',
            title: 'Системный промпт',
            alias: 'specialist-system-prompt',
        );
    }
}
