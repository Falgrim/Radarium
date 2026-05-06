<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\MailingMessageLogStatusEnum;
use App\Models\ApiPostUser;
use App\Models\MailingMessageLog;
use App\Services\ReadTelegramChats;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Image;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;

/**
 * @extends ModelResource<ApiPostUser>
 */
class MailingMessageLogResource extends ModelResource
{
    protected bool $saveFilterState = true;

    protected string $model = MailingMessageLog::class;

    protected string $title = 'Рассылка. Лог';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'created_at';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

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
        return ['view'];
    }

    /**
     * @return Field
     */
    public function fields(): array
    {
        return [];
    }

    public function search(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [];
    }

    public function queryTags(): array
    {
        return [];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            HasOne::make('Сообщение', 'mailingMessage', resource: new MailingMessageResource())->fields([
                Text::make('', 'text', fn($item) => Str::limit($item->text, 150)),
            ]),
            HasOne::make('Пользователь', 'apiPostUser', resource: new ApiPostUserResource())->fields([
                Image::make('Фото', 'photo')->disk('public')->dir(ReadTelegramChats::PHOTO_PATH),
                Text::make('M_ID', 'user_id'),
                Text::make('Логин', 'username'),
                Text::make('Имя', 'first_name'),
                Text::make('Фамилия', 'last_name'),
                Text::make('Телефон', 'phone'),
            ]),
            Text::make('ID сооб.', 'msg_id'),
            Enum::make('Статус', 'status')->attach(MailingMessageLogStatusEnum::class),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Сообщение', 'mailingMessage'),
            Text::make('Пользователь', 'apiPostUser'),
            Text::make('ID сооб.', 'msg_id'),
            Enum::make('Статус', 'status')->attach(MailingMessageLogStatusEnum::class),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function rules(Model $item): array
    {
        return [];
    }

    public function formFields(): array
    {
        return [];
    }
}
