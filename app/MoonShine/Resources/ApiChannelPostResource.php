<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiChannelPost;
use App\MoonShine\Pages\ApiChannelPost\ApiChannelPostIndexPage;
use App\MoonShine\Pages\ApiChannelPost\ApiChannelPostFormPage;
use App\MoonShine\Pages\ApiChannelPost\ApiChannelPostDetailPage;

use Illuminate\Validation\Rule;
use MoonShine\Fields\Checkbox;
use MoonShine\Fields\Date;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\ID;
use MoonShine\Fields\Json;
use MoonShine\Fields\Number;
use MoonShine\Fields\Phone;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Resources\ModelResource;
use MoonShine\Pages\Page;

/**
 * @extends ModelResource<ApiChannelPost>
 */
class ApiChannelPostResource extends ModelResource
{
    protected string $model = ApiChannelPost::class;

    protected string $title = 'Cообщения/Посты';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'post_id';

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
            ApiChannelPostIndexPage::make($this->title()),
            ApiChannelPostFormPage::make(
                $this->getItemID()
                    ? __('moonshine::ui.edit')
                    : __('moonshine::ui.add')
            ),
            ApiChannelPostDetailPage::make(__('moonshine::ui.show')),
        ];
    }

    /**
     * @param ApiChannelPost $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'post' => ['required', 'string', 'min:10'],
            'ai_parse_status' => Rule::enum(ApiChannelPostStatusEnum::class),
        ];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('API ID', 'post_id'),
            Text::make('ID аккаунта', 'api_post_user_id'),
            Text::make('Логин', 'user_login'),
            Date::make('Дата', 'post_date')->withTime(),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Text::make('API ID', 'post_id')->sortable(),
            Text::make('Логин', 'user_login')->sortable(),
            Text::make('Сообщение', 'post', fn($item) => mb_substr($item->post, 0, 100).'...'),
            Date::make('Дата', 'post_date')->withTime()->sortable(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
            Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class)->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('API ID', 'post_id'),
            Text::make('Логин', 'user_login'),
            Text::make('Сообщение', 'post'),
            Date::make('Дата', 'post_date')->withTime(),
            Date::make('Создан', 'created_at')->withTime(),
            Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class),
            Text::make('Ответ ИИ', 'ai_result'),
            Date::make('Запрос к ИИ', 'ai_date')->withTime(),

            HasOne::make('ИИ', 'specialist', resource: new SpecialistResource())->fields([
                Text::make('ID', 'id'),
                Text::make('Опыт работы по специальности', 'experience'),
                Text::make('Владение ПО', 'soft_experience'),
                Text::make('Образование', 'education'),
                Text::make('Требуемый график работы', 'work_schedule'),
                Text::make('Общая продолжительность работы - проекта', 'total_work_project'),
                Text::make('Тип работы', 'type_of_work'),
                Text::make('Желаемая оплата за час', 'price_by_hour'),
                Text::make('Желаемая оплата общая сумма выплат за проект', 'price_by_project'),
                Text::make('Желаемая оплата фиксированная оплата за период времени (месяц)', 'price_by_month'),
                Text::make('О себе', 'about'),
                Text::make('Спец. требования', 'spec_requirements'),
                Text::make('Ссылка на резюме', 'link_resume'),
                Enum::make('Статус', 'status')->attach(SpecialistStatusEnum::class),
                Date::make('Создан', 'created_at')->withTime(),
            ]),

            HasOne::make('Аккаунт', 'apiUser', resource: new ApiPostUserResource())->fields([
                Text::make('ID', 'id'),
                Text::make('Source ID', 'user_id'),
                Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
                Text::make('Логин', 'username'),
                Text::make('Имя', 'first_name'),
                Text::make('Фамилия', 'last_name'),
                Text::make('Телефон', 'phone'),
                Text::make('Тип профиля', 'user_type'),
                Date::make('Онлайн', 'last_online_date')->withTime(),
                Date::make('Создан', 'created_at')->withTime(),
            ]),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('API ID', 'post_id')->disabled()->readonly();
        $fields[] = Text::make('Логин', 'user_login');
        $fields[] = Textarea::make('Сообщение', 'post')->customAttributes(['autocomplete' => 'off']);
        $fields[] = Date::make('Дата', 'post_date')->withTime()->disabled()->readonly();
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();
        $fields[] = Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class)
            ->hint('Вы можете сбросить параметр статуса, чтобы система повторно проверила сообщение.');

        return $fields;
    }
}
