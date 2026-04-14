<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Services\ReadTelegramChats;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiPostUser;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserIndexPage;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserFormPage;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserDetailPage;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MoonShine\Components\Link;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Image;
use MoonShine\Fields\Json;
use MoonShine\Fields\Preview;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Fields\Url;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Pages\Page;

/**
 * @extends ModelResource<ApiPostUser>
 */
class ApiPostUserResource extends ModelResource
{
    protected bool $saveFilterState = false;

    protected string $model = ApiPostUser::class;

    protected string $title = 'Авторы сообщений';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    protected string $column = 'id';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    public function getActiveActions(): array
    {
        return ['view', 'update', 'delete', 'massDelete'];
    }

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function export(): ?ExportHandler
    {
        return null;
    }

    /**
     * @return list<Page>
     */
    public function pages(): array
    {
        return [
            ApiPostUserIndexPage::make($this->title()),
            ApiPostUserFormPage::make(
                $this->getItemID()
                    ? __('moonshine::ui.edit')
                    : __('moonshine::ui.add')
            ),
            ApiPostUserDetailPage::make(__('moonshine::ui.show')),
        ];
    }

    /**
     * @param ApiPostUser $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'username' => ['required', 'string', 'min:1'],
            'first_name' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
        ];
    }

    public function search(): array
    {
        return [
            'user_id',
            'username',
            'phone',
            'posts.post'
        ];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Source ID', 'user_id'),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->nullable(),
            Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class)->nullable(),
            Text::make('Логин', 'username'),
            DateRange::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Url::make('Source ID', 'user_id', fn($item) => 'tg://user?id='.$item->user_id)
                ->title(fn(string $url, Url $ctx) => str_replace('tg://user?id=', '', $url))
                ->blank(),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->sortable(),
            Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class),
            Image::make('Фото', 'photo')->disk('public')->dir(ReadTelegramChats::PHOTO_PATH),
            Url::make('Логин', 'username', fn($item) => $item->username ? 'https://t.me/'.$item->username : '#')
                ->title(fn(string $url, Url $ctx) => str_replace('https://t.me/', '', $url))
                ->blank(),
            Text::make('Имя', 'first_name'),
            Text::make('Фамилия', 'last_name'),
            Text::make('Телефон', 'phone')->sortable(),
            Text::make('Тип профиля', 'user_type')->sortable(),
            Date::make('Онлайн', 'last_online_date')->withTime()->sortable(),
            HasMany::make('Сообщения', 'posts', resource: new ApiChannelPostResource())->onlyLink(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Url::make('Source ID', 'user_id', fn($item) => 'tg://user?id='.$item->user_id)
                ->title(fn(string $url, Url $ctx) => str_replace('tg://user?id=', '', $url))
                ->blank(),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->sortable(),
            Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class),
            Url::make('Логин', 'username', fn($item) => $item->username ? 'https://t.me/'.$item->username : '#')
                ->title(fn(string $url, Url $ctx) => str_replace('https://t.me/', '', $url))
                ->blank(),
            Image::make('Фото', 'photo')->disk('public')->dir(ReadTelegramChats::PHOTO_PATH),
            Text::make('Имя', 'first_name'),
            Text::make('Фамилия', 'last_name'),
            Text::make('Телефон', 'phone')->sortable(),
            Text::make('Тип профиля', 'user_type')->sortable(),
            Date::make('Онлайн', 'last_online_date')->withTime()->sortable(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),

            HasMany::make('Сообщения', 'posts', resource: new ApiChannelPostResource())->fields([
                Text::make('ID', 'id')->sortable(),
                Text::make('API ID', 'post_id')->sortable(),
                Text::make('Логин', 'user_login')->sortable(),
                Text::make('Сообщение', 'post', fn($item) => Str::limit($item->post, 100)),
                Date::make('Дата', 'post_date')->withTime()->sortable(),
                Date::make('Создано', 'created_at')->withTime()->sortable(),
                Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class)->sortable(),
            ]),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Source ID', 'user_id')->disabled()->readonly();
        $fields[] = Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->disabled()->readonly();
        $fields[] = Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class)->readonly();
        $fields[] = Text::make('Логин', 'username');
        $fields[] = Text::make('Имя', 'first_name');
        $fields[] = Text::make('Фамилия', 'last_name');
        $fields[] = Text::make('Телефон', 'phone');
        $fields[] = Text::make('Тип профиля', 'user_type')->disabled()->readonly();
        $fields[] = Date::make('Онлайн', 'last_online_date')->withTime()->disabled()->readonly();
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();
        $fields[] = Json::make('Дополнительные данные', 'external_info')
            ->hint('Техническое поле')
            ->keyValue();


        return $fields;
    }
}
