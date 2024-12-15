<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiChannel;

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

    protected string $title = 'Список источников';

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
        return [];
    }

    public function indexFields(): array
    {
        return [
            Text::make('Название', 'title'),
            Text::make('Ссылка', 'link'),
            Text::make('Описание', 'description'),
            Text::make('Сервис', 'api_ai_id'),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
            Enum::make('Статус', 'status')->attach(ApiChannelStatusEnum::class),
        ];
    }

    public function formFields(): array
    {
        $fields = [];
        $fields[] = Text::make('Название', 'title');
        $fields[] = Text::make('Ссылка', 'link')->hint('Укажите ссылку в формате: https://');
        $fields[] = Text::make('Описание', 'description');

        $fields[] = BelongsTo::make('Сервис ИИ', 'api_ai', resource: new ApiAiResource())
            ->hint('Каким сервисом ИИ будет обработаны сообщения');

        $fields[] = Textarea::make('Промт для ИИ', 'ai_promt')
            ->hint('Укажите какие данные вы хотите получить из сообщения пользователя');

        $fields[] = Enum::make('Тип источника', 'channel_source')
            ->attach(ApiChannelSourceEnum::class)
            ->hint('Выберите какой сервис API будет использован для получения записей из ссылки');

        $fields[] = Switcher::make('Статус', 'status')
            ->onValue(ApiChannelStatusEnum::Active->value)
            ->offValue(ApiChannelStatusEnum::Disabled->value);

        $fields[] = Json::make('Опции для обработки', 'options')
            ->hint('Технические параметры для доп. настройки')
            ->keyValue();

        return $fields;
    }
}
