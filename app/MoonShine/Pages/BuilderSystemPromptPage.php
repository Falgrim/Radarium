<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiDataTypeEnum;
use App\MoonShine\Resources\BuilderResource;
use App\Services\BuilderAiPromptInjector;

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

    protected function systemPromptEditorExtraHint(): ?string
    {
        return 'Для каталога специализаций строителей оставьте в тексте плейсхолдер '.BuilderAiPromptInjector::PLACEHOLDER
            .' — при парсинге (`app:ai_parse:builder`) он заменяется на актуальный список из справочника. Без него ИИ не получит закрытый перечень title.';
    }
}
