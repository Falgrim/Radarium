<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiPostUser;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserIndexPage;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserFormPage;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserDetailPage;

use Illuminate\Validation\Rule;
use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Json;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Resources\ModelResource;
use MoonShine\Pages\Page;

/**
 * @extends ModelResource<ApiPostUser>
 */
class ApiPostUserResource extends ModelResource
{
    protected string $model = ApiPostUser::class;

    protected string $title = 'История аккаунтов';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'created_at';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    public function getActiveActions(): array
    {
        return ['view', 'update', 'delete', 'massDelete'];
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
        ];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Source ID', 'user_id'),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
            Text::make('Логин', 'username'),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Text::make('Source ID', 'user_id')->sortable(),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->sortable(),
            Text::make('Логин', 'username')->sortable(),
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
            Text::make('Source ID', 'user_id')->sortable(),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->sortable(),
            Text::make('Логин', 'username')->sortable(),
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
                Text::make('Сообщение', 'post', fn($item) => mb_substr($item->post, 0, 100).'...'),
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
