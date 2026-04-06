<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiDataTypeEnum;
use App\Services\AiSystemPromptAdminService;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FlexibleRender;
use MoonShine\Decorations\Block;
use MoonShine\Enums\ToastType;
use MoonShine\Http\Responses\MoonShineJsonResponse;
use MoonShine\MoonShineRequest;
use MoonShine\Pages\Page;
use Throwable;

abstract class SystemPromptPage extends Page
{
    public function __construct(
        protected readonly AdminSystemPromptScope $scope,
        protected readonly ApiDataTypeEnum $applyDataType,
        protected readonly string $sectionResourceClass,
        protected readonly string $sectionMenuLabel,
        ?string $title = null,
        ?string $alias = null,
    ) {
        parent::__construct($title ?? 'Системный промпт', $alias);
    }

    public function breadcrumbs(): array
    {
        $resource = app($this->sectionResourceClass);
        $resource->boot();

        return [
            $resource->url() => $this->sectionMenuLabel,
            '#' => 'Системный промпт',
        ];
    }

    /**
     * @throws Throwable
     */
    public function components(): array
    {
        $service = app(AiSystemPromptAdminService::class);
        $initial = $service->getBody($this->scope);
        $textareaId = $this->textareaElementId();

        return [
            Block::make([
                FlexibleRender::make(
                    view('moonshine.custom.system-prompt-editor', [
                        'initialBody' => $initial,
                        'textareaId' => $textareaId,
                        'applyTypeLabel' => (string) ($this->applyDataType->toString() ?? ''),
                        'saveButtonHtml' => $this->saveButtonMarkup($textareaId),
                        'applyButtonHtml' => $this->applyButtonMarkup($textareaId),
                    ])
                ),
            ]),
        ];
    }

    protected function textareaElementId(): string
    {
        return $this->scope->value.'-system-prompt-text';
    }

    protected function saveButtonMarkup(string $textareaId): string
    {
        return (string) ActionButton::make('Сохранить')
            ->primary()
            ->method('saveSystemPrompt', page: $this, message: 'Сохранение...')
            ->withParams(["#{$textareaId}/ai_promt"]);
    }

    protected function applyButtonMarkup(string $textareaId): string
    {
        return (string) ActionButton::make('Применить')
            ->secondary()
            ->method('applyPromptToChannels', page: $this, message: 'Применение...')
            ->withParams(["#{$textareaId}/ai_promt"]);
    }

    public function saveSystemPrompt(MoonShineRequest $request): MoonShineJsonResponse
    {
        $data = $request->validate([
            'ai_promt' => ['nullable', 'string', 'max:100000'],
        ]);

        app(AiSystemPromptAdminService::class)->saveBody(
            $this->scope,
            $data['ai_promt'] ?? ''
        );

        return MoonShineJsonResponse::make()
            ->toast('Системный промпт сохранён', ToastType::SUCCESS)
            ->redirect($this->url());
    }

    public function applyPromptToChannels(MoonShineRequest $request): MoonShineJsonResponse
    {
        $data = $request->validate([
            'ai_promt' => ['nullable', 'string', 'max:100000'],
        ]);

        $prompt = $data['ai_promt'] ?? '';
        $count = app(AiSystemPromptAdminService::class)->applyToChannels(
            $this->applyDataType,
            $prompt
        );

        return MoonShineJsonResponse::make()
            ->toast(
                $count > 0
                    ? "Промпт применён к {$count} источникам"
                    : 'Нет источников с выбранным типом выборки',
                ToastType::SUCCESS
            );
    }
}
