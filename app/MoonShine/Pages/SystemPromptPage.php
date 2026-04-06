<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiDataTypeEnum;
use App\Services\AiSystemPromptAdminService;
use Illuminate\Validation\Rule;
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

        $base = [
            $resource->url() => $this->sectionMenuLabel,
        ];

        if ($this->editingPresetQuery() === null) {
            return $base + ['#' => 'Системный промпт'];
        }

        return $base + [
            $this->url() => 'Системный промпт',
            '#' => $this->editingPresetQuery() === 'new' ? 'Новый промпт' : 'Редактирование',
        ];
    }

    /**
     * @throws Throwable
     */
    public function components(): array
    {
        $service = app(AiSystemPromptAdminService::class);
        $editParam = $this->editingPresetQuery();

        if ($editParam === null) {
            return $this->listComponents($service);
        }

        return $this->editorComponents($service, $editParam);
    }

    /**
     * @throws Throwable
     */
    protected function listComponents(AiSystemPromptAdminService $service): array
    {
        $presets = $service->listPresets($this->scope);
        $rows = [];
        foreach ($presets as $preset) {
            $applied = $service->isPresetCurrentlyApplied($this->applyDataType, $preset);
            $applyBtn = ActionButton::make('Применить')
                ->method('applySavedPresetToChannels', page: $this, message: 'Применение...')
                ->withParams(['preset_id' => '#apply-preset-'.$preset->id]);
            if ($applied) {
                $applyBtn->success();
            } else {
                $applyBtn->primary();
            }
            $applyBtn->customAttributes([
                'class' => 'btn-sm',
            ]);
            $rows[] = [
                'id' => $preset->id,
                'name' => $preset->name,
                'sourceLabel' => $preset->apiSourceAdminLabel(),
                'editUrl' => $this->url().'?preset='.$preset->id,
                'applyButtonHtml' => (string) $applyBtn,
            ];
        }

        return [
            Block::make([
                FlexibleRender::make(
                    view('moonshine.custom.system-prompt-list', [
                        'createUrl' => $this->url().'?preset=new',
                        'rows' => $rows,
                    ])
                ),
            ]),
        ];
    }

    /**
     * @throws Throwable
     */
    protected function editorComponents(AiSystemPromptAdminService $service, string $editParam): array
    {
        $textareaId = $this->textareaElementId();
        $presetId = null;
        $initialName = '';
        $initialSource = array_key_first($service->selectableAiSources()) ?? 'yandexgtp4';
        $initialBody = '';

        if ($editParam !== 'new' && ctype_digit($editParam)) {
            $preset = $service->findPresetForScope($this->scope, (int) $editParam);
            if ($preset === null) {
                abort(404);
            }
            $presetId = $preset->id;
            $initialName = $preset->name;
            $initialSource = (string) $preset->api_source;
            $initialBody = (string) ($preset->body ?? '');
        }

        $sourceOptions = $service->selectableAiSources();

        return [
            Block::make([
                FlexibleRender::make(
                    view('moonshine.custom.system-prompt-editor', [
                        'listUrl' => $this->url(),
                        'presetId' => $presetId,
                        'initialName' => $initialName,
                        'initialSource' => $initialSource,
                        'initialBody' => $initialBody,
                        'sourceOptions' => $sourceOptions,
                        'textareaId' => $textareaId,
                        'applyTypeLabel' => (string) ($this->applyDataType->toString() ?? ''),
                        'saveButtonHtml' => $this->saveButtonMarkup($textareaId, $presetId),
                    ])
                ),
            ]),
        ];
    }

    protected function textareaElementId(): string
    {
        return $this->scope->value.'-system-prompt-text';
    }

    protected function editingPresetQuery(): ?string
    {
        $p = request()->query('preset');
        if ($p === null || $p === '') {
            return null;
        }

        $p = (string) $p;
        if ($p === 'new') {
            return 'new';
        }

        return ctype_digit($p) ? $p : null;
    }

    protected function saveButtonMarkup(string $textareaId, ?int $presetId): string
    {
        $nameId = $this->scope->value.'-system-prompt-name';
        $sourceId = $this->scope->value.'-system-prompt-api-source';
        $params = [
            'name' => '#'.$nameId,
            'api_source' => '#'.$sourceId,
            'ai_promt' => '#'.$textareaId,
        ];
        if ($presetId !== null) {
            $params['preset_id'] = '#'.$this->scope->value.'-system-prompt-preset-id';
        }

        return (string) ActionButton::make('Сохранить')
            ->primary()
            ->method('saveSystemPromptPreset', page: $this, message: 'Сохранение...')
            ->withParams($params);
    }

    public function saveSystemPromptPreset(MoonShineRequest $request): MoonShineJsonResponse
    {
        $service = app(AiSystemPromptAdminService::class);
        $allowedSources = $service->allowedApiSourceKeys();
        $data = $request->validate([
            'preset_id' => ['sometimes', 'nullable', 'integer', 'exists:admin_system_prompt_presets,id'],
            'name' => ['required', 'string', 'max:255'],
            'api_source' => ['required', 'string', Rule::in($allowedSources)],
            'ai_promt' => ['nullable', 'string', 'max:100000'],
        ]);

        $id = isset($data['preset_id']) ? (int) $data['preset_id'] : null;

        $service->savePreset(
            $this->scope,
            $data['name'],
            $data['api_source'],
            $data['ai_promt'] ?? '',
            $id,
        );

        return MoonShineJsonResponse::make()
            ->toast('Промпт успешно сохранён', ToastType::SUCCESS)
            ->redirect($this->url());
    }

    public function applySavedPresetToChannels(MoonShineRequest $request): MoonShineJsonResponse
    {
        $data = $request->validate([
            'preset_id' => ['required', 'integer', 'exists:admin_system_prompt_presets,id'],
        ]);

        $count = app(AiSystemPromptAdminService::class)->applyPresetToChannels(
            $this->scope,
            $this->applyDataType,
            (int) $data['preset_id'],
        );

        return MoonShineJsonResponse::make()
            ->toast(
                $count > 0
                    ? "Промпт применён к {$count} источникам"
                    : 'Нет подходящих источников: проверьте тип выборки и (для пресета с одним провайдером) привязку сервиса ИИ у каналов',
                ToastType::SUCCESS
            );
    }
}
