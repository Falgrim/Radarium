<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiDataTypeEnum;
use App\MoonShine\Resources\BuilderResource;

final class BuilderSystemPromptPage extends SystemPromptPage
{
    public function __construct()
    {
        parent::__construct(
            scope: AdminSystemPromptScope::Builder,
            applyDataType: ApiDataTypeEnum::Builder,
            sectionResourceClass: BuilderResource::class,
            sectionMenuLabel: 'Строительство',
            title: 'Системный промпт',
            alias: 'builder-system-prompt',
        );
    }
}
