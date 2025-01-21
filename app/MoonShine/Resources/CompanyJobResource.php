<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Models\Specialist;
use Illuminate\Database\Eloquent\Model;
use App\Models\CompanyJob;

use Illuminate\Support\Str;
use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<CompanyJob>
 */
class CompanyJobResource extends ModelResource
{
    protected string $model = CompanyJob::class;

    protected string $title = 'Вакансии';

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

    public function getActiveActions(): array
    {
        return ['view', 'update', 'delete', 'massDelete'];
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
     * @param Specialist $item
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
            ID::make()->sortable(),
            HasOne::make('Аккаунт', 'apiPostUser', resource: new ApiPostUserResource())->fields([
                //Enum::make('Тип источника', 'channel_source')->attach(ApiChannelSourceEnum::class),
                Text::make('Логин', 'username'),
                Text::make('Имя', 'fio', fn($item) => 'ID ['.$item->id.']: '.trim($item->last_name.' '.$item->first_name)),
                //Date::make('Создан', 'created_at')->withTime(),
            ]),
            HasOne::make('Пост', 'apiChannelPost', resource: new ApiChannelPostResource())->fields([
                //Text::make('ID', 'id'),
                Date::make('Дата пуб.', 'post_date'),
                Date::make('Создан', 'created_at'),
            ]),
            Text::make('Описание', 'description', fn($item) => Str::limit($item->description, 100)),
            Enum::make('Статус', 'status')->attach(SpecialistStatusEnum::class)->sortable(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Тип сообщения', 'ai_type'),
            Text::make('Подробнее о типе', 'ai_reason'),
            Text::make('Название компании', 'company_name'),
            Text::make('Должность', 'position'),
            Text::make('Предлагаемый оклад (мин.)', 'min_price'),
            Text::make('Предлагаемый оклад (макс.)', 'max_price'),
            Text::make('Обязанности', 'duty'),
            Text::make('Требования', 'requirement'),
            Text::make('График', 'work_schedule'),
            Text::make('Тип работы', 'type_of_work'),
            Text::make('Описание проекта', 'description'),
            Text::make('Срок найма', 'period'),
            Text::make('Доп. условия', 'extra_conditions'),
            Enum::make('Статус', 'status')->attach(CompanyJobStatusEnum::class),
            Date::make('Создан', 'created_at')->withTime(),

            HasOne::make('Сообщение', 'apiChannelPost', resource: new ApiChannelPostResource())->fields([
                Text::make('ID', 'id'),
                Text::make('API ID', 'post_id'),
                Text::make('Логин', 'user_login'),
                Text::make('Сообщение', 'post'),
                Date::make('Дата публикации', 'post_date')->withTime(),
                Date::make('Создан', 'created_at')->withTime(),
                Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class),
                Text::make('Ответ ИИ', 'ai_result'),
                Date::make('Запрос к ИИ', 'ai_date')->withTime()
            ]),

            HasOne::make('Аккаунт', 'apiPostUser', resource: new ApiPostUserResource())->fields([
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

        $fields[] = Text::make('Название компании', 'company_name');
        $fields[] = Text::make('Должность', 'position');
        $fields[] = Text::make('Предлагаемый оклад (мин.)', 'min_price');
        $fields[] = Text::make('Предлагаемый оклад (макс.)', 'max_price');
        $fields[] = Textarea::make('Обязанности', 'duty')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Требования', 'requirement')->customAttributes(['rows' => '5']);
        $fields[] = Text::make('График', 'work_schedule');
        $fields[] = Text::make('Тип работы', 'type_of_work');
        $fields[] = Textarea::make('Описание проекта', 'description')->customAttributes(['rows' => '5']);
        $fields[] = Text::make('Срок найма', 'period');
        $fields[] = Text::make('Доп. условия', 'extra_conditions');
        $fields[] = Enum::make('Статус', 'status')->attach(CompanyJobStatusEnum::class);
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();

        return $fields;
    }
}
