<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\IsCompanyEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiChannel;

use Illuminate\Validation\Rule;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Json;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

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

    public function getActiveActions(): array
    {
        return ['create', 'view', 'update', 'delete'];
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
        return [
            'title' => ['required', 'string', 'min:3'],
            'link' => ['required', 'url:http,https'],
            'description' => ['string', 'min:3'],
            'ai_promt' => ['required', 'string', 'min:10'],
            'api_ai_id' => ['exists:App\Models\ApiAi,id'],
            'channel_source' => Rule::enum(ApiChannelSourceEnum::class),
            'status' => Rule::enum(ApiChannelStatusEnum::class),
            'is_company' => Rule::enum(IsCompanyEnum::class),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Ссылка', 'link'),
            Text::make('Описание', 'description'),
            BelongsTo::make('Сервис', 'apiAi'),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
            Enum::make('Тип выборки', 'is_company')->attach(IsCompanyEnum::class),
            Enum::make('Статус', 'status')->attach(ApiChannelStatusEnum::class),
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
            Enum::make('Тип выборки', 'is_company')->attach(IsCompanyEnum::class),
            Enum::make('Статус', 'status')->attach(ApiChannelStatusEnum::class),
            Text::make('Опции для обработки', 'options', fn($item) => $item->options ? json_encode($item->options) : ''),
        ];
    }

    public function formFields(): array
    {
        $fields = [];
        $fields[] = Text::make('Название', 'title');
        $fields[] = Text::make('Ссылка', 'link')->hint('Укажите ссылку в формате: https://');
        $fields[] = Text::make('Описание', 'description');

        $fields[] = BelongsTo::make('Сервис ИИ', 'apiAi', resource: new ApiAiResource())
            ->hint('Каким сервисом ИИ будет обработаны сообщения');

        $fields[] = Enum::make('Тип выборки', 'is_company')
            ->hint('От типа зависит какая сущность в БД будет отвечать за сохранение данных (резюме или вакансия)')
            ->attach(IsCompanyEnum::class);

        $fields[] = Textarea::make('Промт для ИИ', 'ai_promt')
            ->customAttributes(['rows' => '5'])
            ->hint('Обязательные поля для типа "Частный" (резюме) (не меняйте регистр или отступы названия полей, иначе система не сможет сопоставить данные с сущностью в БД):
            <br >-Опыт работы по специальности;
            <br >-Владение ПО;
            <br >-Образование;
            <br >-Требуемый график работы;
            <br >-Общая продолжительность работы (за проект);
            <br >-Тип работы: офис, удаленка, гибрид;
            <br >-Желаемая оплата за час;
            <br >-Желаемая оплата за проект;
            <br >-Желаемая оплата за месяц;
            <br >-О себе;
            <br >-Спец. требования;
            <br >-Ссылка на резюме;<br /><br />
            Пример:
            <br >Ты HR и рассматриваешь вакансии кандидатов, которые публикуются в свободном текстовом формате. Выдели основные характеристики в json формате. Разбей информацию по парамаетрам из списка, если какого-то параметра нет, то не указывай его: Опыт работы по специальности; Владение ПО; Образование; Требуемый график работы; Общая продолжительность работы (за проект); Тип работы: офис, удаленка, гибрид; Желаемая оплата за час; Желаемая оплата за проект; Желаемая оплата за месяц; О себе; Спец. требования; Ссылка на резюме;');

        /*$fields[] = Textarea::make('Пример промта', 'ai_promt_example')
            ->default('Ты HR и рассматриваешь вакансии кандидатов, которые публикуются в свободном текстовом формате. Выдели основные характеристики в json формате. Разбей информацию по парамаетрам из списка, если какого-то параметра нет, то не указывай его: Опыт работы по специальности; Владение ПО; Образование; Требуемый график работы; Общая продолжительность работы (за проект); Тип работы: офис, удаленка, гибрид; Желаемая оплата за час; Желаемая оплата за проект; Желаемая оплата за месяц; О себе; Спец. требования; Ссылка на резюме;')
            ->customAttributes(['rows' => '5'])
            ->readonly();*/

        $fields[] = Enum::make('Тип источника', 'channel_source')
            ->attach(ApiChannelSourceEnum::class)
            ->hint('Выберите какой сервис API будет использован для получения записей из ссылки');

        $fields[] = Enum::make('Статус', 'status')
            ->attach(ApiChannelStatusEnum::class);

        $fields[] = Json::make('Опции для обработки', 'options')
            ->hint('Технические параметры для доп. настройки<br />Для Telegram обязательны параметры: api_id, api_hash, reply_id (если требуется брать данные только из одного чата канала/группы)')
            ->keyValue();

        return $fields;
    }
}
