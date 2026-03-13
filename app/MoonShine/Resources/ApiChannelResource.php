<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiAi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiChannel;

use Illuminate\Validation\Rule;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FormBuilder;
use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Json;
use MoonShine\Fields\Select;
use MoonShine\Fields\Preview;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Http\Responses\MoonShineJsonResponse;
use MoonShine\MoonShineRequest;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;
use MoonShine\Enums\ToastType;

/**
 * @extends ModelResource<ApiChannel>
 */
class ApiChannelResource extends ModelResource
{
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
     * Кнопки на странице индекса (массовое переключение сервиса ИИ).
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
        ];
    }

    /**
     * Массовое обновление сервиса ИИ для всех источников.
     */
    public function massUpdateAiService(MoonShineRequest $request): MoonShineJsonResponse
    {
        $apiAiId = $request->integer('api_ai_id');
        if (!$apiAiId || !ApiAi::query()->where('id', $apiAiId)->exists()) {
            return MoonShineJsonResponse::make()
                ->toast('Выберите корректный сервис ИИ', ToastType::ERROR);
        }

        $updated = ApiChannel::query()->update(['api_ai_id' => $apiAiId]);

        return MoonShineJsonResponse::make()
            ->toast(
                "Сервис ИИ обновлён для всех источников ({$updated} шт.)",
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
     * @param ApiChannel $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        $regionRules = ['nullable', 'string', 'max:100'];
        $regionKeys = array_keys(config('regions', []));
        if (count($regionKeys) > 0) {
            $regionRules[] = Rule::in(array_merge([''], $regionKeys));
        }

        return [
            'title' => ['required', 'string', 'min:3'],
            'link' => ['required', 'url:http,https'],
            'description' => ['string', 'min:3'],
            'ai_promt' => ['required', 'string', 'min:10'],
            'api_ai_id' => ['exists:App\Models\ApiAi,id'],
            'channel_source' => Rule::enum(ApiChannelSourceEnum::class),
            'status' => Rule::enum(ApiChannelStatusEnum::class),
            'is_company' => Rule::enum(ApiDataTypeEnum::class),
            'region' => $regionRules,
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Ссылка', 'link'),
            //Text::make('Описание', 'description'),
            BelongsTo::make('Сервис', 'apiAi')->setColumn('api_ai_id')->sortable(),
            //Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
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
            Text::make('Опции для обработки', 'options', fn($item) => $item->options ? json_encode($item->options) : ''),
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

        $fields[] = BelongsTo::make('Сервис ИИ', 'apiAi', resource: new ApiAiResource())
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
            ->hint('Необязательно. Город-миллионник РФ. Будет проставлен всем сообщениям из этого источника (в записи строителей или специалистов в зависимости от типа выборки).');

        $fields[] = Textarea::make('Промт для ИИ', 'ai_promt')
            ->hint('Не меняйте промт без предварительного тестирования в самом ИИ, так как даже при небольших изменениях может поменяться результат и формат ответа')
            ->customAttributes(['rows' => '15']);

        $fields[] = Enum::make('Тип источника', 'channel_source')
            ->attach(ApiChannelSourceEnum::class)
            ->hint('Выберите какой сервис API будет использован для получения записей из ссылки');

        $fields[] = Enum::make('Статус', 'status')
            ->attach(ApiChannelStatusEnum::class);

        $fields[] = Json::make('Опции для обработки', 'options')
            ->hint('Технические параметры для доп. настройки<br />Для Telegram обязательны параметры: api_id, api_hash, reply_to_msg_id (если требуется брать данные только из одного чата канала/группы)')
            ->keyValue();

        return $fields;
    }

}
