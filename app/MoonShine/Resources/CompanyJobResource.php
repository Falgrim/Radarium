<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use Illuminate\Database\Eloquent\Model;
use App\Models\CompanyJob;

use Illuminate\Support\Str;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Select;
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
 * @extends ModelResource<CompanyJob>
 */
class CompanyJobResource extends ModelResource
{
    protected string $model = CompanyJob::class;

    protected string $title = 'Вакансии';

    protected string $sortColumn = 'post_date';

    protected string $sortDirection = 'DESC';

    public string $column = 'created_at';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    protected bool $columnSelection = true;

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

    public function search(): array
    {
        return ['post.post', 'user.username'];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            DateRange::make('Дата сообщения', 'post_date')->withTime(),
            DateRange::make('Создан', 'created_at')->withTime(),
            Select::make('Статус', 'status')
                ->options(
                    CompanyJobStatusEnum::getList()
                ),
        ];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Пользователь', 'username', fn($item) => $item->apiPostUser?->username),
            Text::make('Источник', 'source', fn($item) => $item?->post?->channel?->title),
            Text::make('Пост', 'post', fn($item) => Str::limit($item?->post?->post, 200)),
            Text::make('ИИ представление', 'ai_reason'),
            Date::make('Дата сообщения', 'post_date')->withTime()->sortable(),
            Enum::make('Статус', 'status')->attach(CompanyJobStatusEnum::class)->sortable(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Тип сообщения', 'ai_type'),
            Text::make('Подробнее о типе', 'ai_reason'),
            Date::make('Дата сообщения', 'post_date')->withTime(),
            Text::make('Название компании', 'company_name'),
            Text::make('Должность', 'position'),
            Text::make('Специализация', 'specialities', fn($item) => implode(', ', $item->specialtiesWithTitle())),
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
                Text::make('Источник', 'source', fn($item) => $item->channel->title),
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

        $dictionarySpeciality = DictionarySpeciality::where('api_data_type_id', ApiDataTypeEnum::Specialist)->get();
        $dictionaryArr = [];
        foreach ($dictionarySpeciality as $row) {
            $dictionaryArr[$row['id']] = $row['title'];
        }

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Date::make('Дата сообщения', 'post_date')->withTime()->disabled()->readonly();
        $fields[] = Text::make('Название компании', 'company_name');
        $fields[] = Select::make('Специализация', 'specialitiesForMoonshine')
            ->options($dictionaryArr)
            ->multiple()
            ->nullable()
            ->searchable();
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
