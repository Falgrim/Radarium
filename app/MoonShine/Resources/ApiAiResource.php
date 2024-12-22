<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Models\Configuration;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiAi;

use Illuminate\Validation\Rule;
use MoonShine\Fields\Date;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Json;
use MoonShine\Fields\Phone;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Td;
use MoonShine\Fields\Text;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<ApiAi>
 */
class ApiAiResource extends ModelResource
{
    protected string $model = ApiAi::class;

    protected string $title = 'Сервисы ИИ';

    protected string $sortColumn = 'title';

    protected string $sortDirection = 'ASC';

    protected string $column = 'title';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    public function search(): array
    {
        return [];
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
     * @param ApiAi $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'title' => ['required', 'string', 'min:3'],
            'description' => ['required', 'string', 'min:3'],
            'api_source' => Rule::enum(ApiAiSourceEnum::class),
            'status' => Rule::enum(ApiAiStatusEnum::class),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Описание', 'description'),
            Enum::make('API сервис', 'api_source')->attach(ApiAiSourceEnum::class),
            Enum::make('Статус', 'status')->attach(ApiAiStatusEnum::class),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Описание', 'description'),
            Enum::make('API сервис', 'api_source')->attach(ApiAiSourceEnum::class),
            Enum::make('Статус', 'status')->attach(ApiAiStatusEnum::class),
            Text::make('Опции запуска', 'options', fn($item) => $item->options ? json_encode($item->options) : ''),
        ];
    }

    public function formFields(): array
    {
        $fields = [];
        $fields[] = Text::make('Название', 'title');
        $fields[] = Text::make('Описание', 'description');
        $fields[] = Enum::make('API сервис', 'api_source')->attach(ApiAiSourceEnum::class);
        $fields[] = Switcher::make('Статус', 'status')
            ->onValue(ApiAiStatusEnum::Active->value)
            ->offValue(ApiAiStatusEnum::Disabled->value);
        $fields[] = Json::make('Опции для запуска', 'options')
            ->hint('Технические параметры для доп. настройки<br />Для '.ApiAiSourceEnum::YandexGTP4->toString().' обязательны параметры: Bearer и Folder_id (подробнее: https://yandex.cloud/ru/docs/foundation-models/quickstart/yandexgpt#api_2)')
            ->keyValue();

        return $fields;
    }
}
