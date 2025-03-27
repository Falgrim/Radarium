<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiPostUser;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserIndexPage;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserFormPage;
use App\MoonShine\Pages\ApiPostUser\ApiPostUserDetailPage;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Json;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Pages\Page;

/**
 * @extends ModelResource<ApiPostUser>
 */
class ApiPostUserResource extends ModelResource
{
    protected string $model = ApiPostUser::class;

    protected string $title = 'Специалисты (аккаунты)';

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
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
            Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class),
            Text::make('Логин', 'username'),
            DateRange::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Text::make('Source ID', 'user_id')->sortable(),
            Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class)->sortable(),
            Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class),
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
            Enum::make('Тип аккаунта', 'is_company')->attach(ApiDataTypeEnum::class),
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
