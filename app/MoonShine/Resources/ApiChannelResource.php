<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiAi;
use App\Models\ApiChannel;
use App\Services\AiReprocessService;
use App\Services\AiSystemPromptAdminService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FormBuilder;
use MoonShine\Components\MoonShineComponent;
use MoonShine\Decorations\Block;
use MoonShine\Enums\ToastType;
use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Field;
use MoonShine\Fields\ID;
use MoonShine\Fields\Json;
use MoonShine\Fields\Preview;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Select;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Http\Responses\MoonShineJsonResponse;
use MoonShine\MoonShineRequest;
use MoonShine\Resources\ModelResource;

/**
 * @extends ModelResource<ApiChannel>
 */
class ApiChannelResource extends ModelResource
{
    protected bool $saveFilterState = true;

    private const string AI_PROMPT_FIELD_LABEL = 'Промт для ИИ';

    protected string $model = ApiChannel::class;

    protected string $title = 'Источники сообщений';

    protected string $sortColumn = 'channel_source';

    protected string $sortDirection = 'ASC';

    public string $column = 'title';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    public function search(): array
    {
        return [];
    }

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function export(): ?ExportHandler
    {
        return null;
    }

    public function getActiveActions(): array
    {
        return ['create', 'view', 'update', 'delete'];
    }

    /**
     * Кнопки массового управления ИИ на странице индекса.
     *
     * @return list<ActionButton>
     */
    public function actions(): array
    {
        $apiAiOptions = ApiAi::query()->pluck('title', 'id')->toArray();
        if (empty($apiAiOptions)) {
            return [];
        }

        return [
            ActionButton::make('Сменить сервис ИИ для всех', '#')
                ->icon('heroicons.cpu-chip')
                ->secondary()
                ->inOffCanvas(
                    fn () => 'Массовое переключение сервиса ИИ',
                    fn () => FormBuilder::make()
                        ->name('mass-ai-service-form')
                        ->fields([
                            Select::make('Сервис ИИ', 'api_ai_id')
                                ->options($apiAiOptions)
                                ->required()
                                ->hint('Выбранный сервис будет назначен всем источникам сообщений'),
                        ])
                        ->asyncMethod('massUpdateAiService')
                        ->submit('Применить для всех источников'),
                    isLeft: false
                ),
            ActionButton::make('Обработать необработанные сообщения', '#')
                ->icon('heroicons.arrow-path')
                ->warning()
                ->inOffCanvas(
                    fn () => 'Повторная обработка сообщений',
                    fn () => FormBuilder::make()
                        ->name('requeue-unprocessed-ai-messages-form')
                        ->fields([
                            Preview::make(
                                '',
                                'requeue_hint',
                                static fn (): string => 'Сообщения со статусами «Ошибка обработки» и «Нет данных» '
                                    .'будут возвращены в очередь. Все сообщения со статусом «В очереди» '
                                    .'будут обработаны назначенными источникам сервисами ИИ.'
                            ),
                        ])
                        ->asyncMethod('requeueUnprocessedAiMessages')
                        ->submit('Запустить обработку'),
                    isLeft: false
                ),
        ];
    }

    /**
     * Массовое обновление сервиса ИИ для всех источников.
     */
    public function massUpdateAiService(MoonShineRequest $request): MoonShineJsonResponse
    {
        $apiAiId = $request->integer('api_ai_id');
        $apiAi = $apiAiId ? ApiAi::query()->find($apiAiId) : null;

        if (! $apiAi) {
            return MoonShineJsonResponse::make()
                ->toast('Выберите корректный сервис ИИ', ToastType::ERROR);
        }

        $updated = ApiChannel::query()->update(['api_ai_id' => $apiAiId]);
        $pending = app(AiReprocessService::class)->dispatchProcessing();

        return MoonShineJsonResponse::make()
            ->toast(
                "Сервис «{$apiAi->title}» назначен всем источникам ({$updated} шт.). "
                    ."Необработанные сообщения поставлены в фоновую обработку ({$pending} шт.)",
                ToastType::SUCCESS
            )
            ->redirect(to_page(resource: self::class));
    }

    /**
     * Возвращает неуспешно обработанные сообщения в очередь и запускает ИИ.
     */
    public function requeueUnprocessedAiMessages(): MoonShineJsonResponse
    {
        $service = app(AiReprocessService::class);

        $requeued = $service->requeue();
        $pending = $service->dispatchProcessing();

        return MoonShineJsonResponse::make()
            ->toast(
                "Фоновая обработка запущена для {$pending} сообщ. "
                    ."Возвращено в очередь после ошибок или отсутствия данных: {$requeued}",
                ToastType::SUCCESS
            )
            ->redirect(to_page(resource: self::class));
    }

    /**
     * @return list<MoonShineComponent|Field>
     */
    public function fields(): array
    {
        return [
            Block::make([
                ID::make()->sortable(),
            ]),
        ];
    }

    /**
     * @param  ApiChannel  $item
     * @return array<string, string[]|string>
     *
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        $regionRules = ['nullable', 'string', 'max:100'];
        $regionKeys = array_keys(config('regions', []));
        if (count($regionKeys) > 0) {
            $regionRules[] = Rule::in(array_merge([''], $regionKeys));
        }

        $isCreate = ! $item->exists;

        $apiAiRules = $isCreate
            ? ['required', 'integer', Rule::notIn([0]), Rule::exists(ApiAi::class, 'id')]
            : ['nullable', 'integer', Rule::notIn([0]), Rule::exists(ApiAi::class, 'id')];

        $isCompanyRules = $isCreate
            ? ['required', Rule::enum(ApiDataTypeEnum::class)]
            : ['nullable', Rule::enum(ApiDataTypeEnum::class)];

        return [
            'title' => ['required', 'string', 'min:3'],
            'link' => ['required', 'url:http,https'],
            'description' => ['string', 'min:3'],
            'ai_promt' => ['nullable', 'string', $this->aiPromptRule($item)],
            'api_ai_id' => $apiAiRules,
            'channel_source' => Rule::enum(ApiChannelSourceEnum::class),
            'status' => Rule::enum(ApiChannelStatusEnum::class),
            'is_company' => $isCompanyRules,
            'region' => $regionRules,
        ];
    }

    public function prepareForValidation(): void
    {
        $req = request();
        $raw = $req->input('ai_promt');
        $trimmed = is_string($raw) ? trim($raw) : '';
        if ($trimmed !== '') {
            $req->merge(['ai_promt' => $trimmed]);

            return;
        }

        $apiAiId = $req->input('api_ai_id');
        if ($apiAiId === null || $apiAiId === '' || (int) $apiAiId === 0) {
            return;
        }

        $rawIsCompany = $req->input('is_company');
        if ($rawIsCompany === null || $rawIsCompany === '') {
            return;
        }

        $dataType = ApiDataTypeEnum::tryFrom((int) $rawIsCompany);
        if ($dataType === null) {
            return;
        }

        $apiAi = ApiAi::query()->find((int) $apiAiId);
        if ($apiAi === null) {
            return;
        }

        $body = app(AiSystemPromptAdminService::class)->resolveActivePresetBodyForChannel($dataType, $apiAi);
        if ($body !== null && mb_strlen($body) >= 10) {
            $req->merge(['ai_promt' => $body]);
        }
    }

    /**
     * @return \Closure(string, mixed, \Closure(string): void): void
     */
    private function aiPromptRule(Model $item): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($item): void {
            $trim = trim((string) $value);
            if (mb_strlen($trim) >= 10) {
                return;
            }

            $req = request();
            $apiAiId = $req->input('api_ai_id');
            $isCompany = $req->input('is_company');
            $isCreate = ! $item->exists;

            if ($isCreate) {
                if ($apiAiId === null || $apiAiId === '' || (int) $apiAiId === 0) {
                    return;
                }
                if ($isCompany === null || $isCompany === '') {
                    return;
                }
            } else {
                if ($apiAiId === null || $apiAiId === '' || (int) $apiAiId === 0
                    || $isCompany === null || $isCompany === '') {
                    $fail($this->aiPromptStandardRequiredMessage());

                    return;
                }
            }

            if ($trim !== '' && mb_strlen($trim) < 10) {
                $fail($this->aiPromptStandardMinMessage());

                return;
            }

            $dataType = ApiDataTypeEnum::tryFrom((int) $isCompany);
            $apiAi = ApiAi::query()->find((int) $apiAiId);
            if ($dataType === null || $apiAi === null) {
                $fail($this->aiPromptStandardRequiredMessage());

                return;
            }

            $promptService = app(AiSystemPromptAdminService::class);
            if ($promptService->adminScopeForDataType($dataType) === null) {
                $fail($this->aiPromptStandardRequiredMessage());

                return;
            }

            $body = $promptService->resolveActivePresetBodyForChannel($dataType, $apiAi);
            if ($body !== null && mb_strlen($body) >= 10) {
                return;
            }

            $fail($this->aiPromptStandardRequiredMessage());
        };
    }

    private function aiPromptStandardRequiredMessage(): string
    {
        return __('validation.required', ['attribute' => self::AI_PROMPT_FIELD_LABEL]);
    }

    private function aiPromptStandardMinMessage(): string
    {
        return __('validation.min.string', [
            'attribute' => self::AI_PROMPT_FIELD_LABEL,
            'min' => 10,
        ]);
    }

    public function indexFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Ссылка', 'link'),
            // Text::make('Описание', 'description'),
            BelongsTo::make('Сервис', 'apiAi')->setColumn('api_ai_id')->sortable(),
            // Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
            Enum::make('Тип выборки', 'is_company')->attach(ApiDataTypeEnum::class)->sortable(),
            Text::make('Регион', 'region')->sortable(),
            Date::make('Дата начала', 'post_from_date')->format('d.m.Y')->sortable(),
            Enum::make('Статус', 'status')->attach(ApiChannelStatusEnum::class)->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Ссылка', 'link'),
            Text::make('Описание', 'description'),
            BelongsTo::make('Сервис', 'apiAi'),
            Text::make('Промт для ИИ', 'ai_promt'),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
            Enum::make('Тип выборки', 'is_company')->attach(ApiDataTypeEnum::class),
            Text::make('Регион', 'region'),
            Enum::make('Статус', 'status')->attach(ApiChannelStatusEnum::class),
            Text::make('Опции для обработки', 'options', fn ($item) => $item->options ? json_encode($item->options) : ''),
        ];
    }

    public function formFields(): array
    {
        $fields = [];
        $fields[] = Text::make('Название', 'title');
        /*$fields[] = Text::make('Регион', 'region')
            ->nullable();*/
        $fields[] = Text::make('Ссылка', 'link')->hint('Укажите ссылку в формате: https://');
        $fields[] = Text::make('Описание', 'description');

        $fields[] = BelongsTo::make('Сервис ИИ', 'apiAi', resource: app(ApiAiResource::class))
            ->hint('Каким сервисом ИИ будет обработаны сообщения');

        $fields[] = Enum::make('Тип выборки', 'is_company')
            ->hint('От типа зависит какая сущность в БД будет отвечать за сохранение данных (резюме или вакансия)')
            ->attach(ApiDataTypeEnum::class);

        $fields[] = Date::make('Дата начала', 'post_from_date')
            ->hint('Укажите с какой даты публикации сообщений должен быть просканирован при первом запуске источник');

        $regionOptions = ['' => '— не указан'] + config('regions', []);
        $fields[] = Select::make('Регион', 'region')
            ->options($regionOptions)
            ->nullable()
            ->hint('Необязательно. Каноническое название из справочника регионов/городов (config/regions.php). Для записей без региона в тексте сообщения подставится это значение; если ИИ извлёк регион из текста — он важнее и тоже нормализуется к справочнику.');

        $fields[] = Textarea::make('Промт для ИИ', 'ai_promt')
            ->hint('Если оставить пустым, подставится текст из сохранённого системного промпта для выбранных «Сервис ИИ» и «Тип выборки» (раздел «Системный промпт»). Имеет приоритет текст, введённый вручную. Не меняйте промт без предварительного тестирования в самом ИИ.')
            ->customAttributes(['rows' => '15']);

        $fields[] = Enum::make('Тип источника', 'channel_source')
            ->attach(ApiChannelSourceEnum::class)
            ->hint('Выберите какой сервис API будет использован для получения записей из ссылки');

        $fields[] = Enum::make('Статус', 'status')
            ->attach(ApiChannelStatusEnum::class);

        $fields[] = Json::make('Опции для обработки', 'options')
            ->hint('Технические параметры (без секретов). Telegram: api_id, api_hash; при необходимости reply_to_msg_id. VK: токен в .env (VK_SERVICE_TOKEN); опционально screen_name или owner_id; репосты по умолчанию сохраняются с текстом из copy_history (отключить: VK_SKIP_REPOSTS=true); аватары VK — VK_DOWNLOAD_AVATARS и файлы vk_{id}.jpg в каталоге как у Telegram.')
            ->keyValue();

        return $fields;
    }
}
