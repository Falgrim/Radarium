<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\DictionarySpeciality;
use App\Models\SpecialistSpeciality;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Specialist;

use Illuminate\Support\Str;
use MoonShine\Fields\Checkbox;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Json;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasManyThrough;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Select;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\QueryTags\QueryTag;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<Specialist>
 */
class SpecialistResource extends ModelResource
{
    protected string $model = Specialist::class;

    protected string $title = 'Проектирование';

    protected string $sortColumn = 'post_date';

    protected string $sortDirection = 'DESC';

    public string $column = 'created_at';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    protected bool $columnSelection = true;

    protected array $with = ['post', 'user'];

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
        $channels = ApiChannel::select('id', 'title')->get()->toArray();
        $channelSelect = [
            '' => 'Не выбрано',
        ];
        foreach ($channels as $channel) {
            $channelSelect[$channel['id']] = $channel['title'];
        }

        return [
            Text::make('ID', 'id'),
            DateRange::make('Дата сообщения', 'post_date')->withTime(),
            DateRange::make('Создан', 'created_at')->withTime(),
            /*Select::make('Источник', 'post.api_channel_id')->options(
                $channelSelect
            ),*/
            Select::make('Статус', 'status')
                ->options(
                    ApiPostAiStatusEnum::getList()
                ),
        ];
    }

    protected function onBoot(): void
    {
        //dd($this, $this->getQuery());
        if (!is_null($this->getItem())) {
            $this->formPage()
                ->setBreadcrumbs([
                    $this->indexPage()->url() => $this->title(),
                    '#' => $this->getItem()->last_name.' '.$this->getItem()->first_name.' '.$this->getItem()->middle_name,
                ]);

            $this->detailPage()->setBreadcrumbs([
                $this->indexPage()->url() => $this->title(),
                '#' => $this->getItem()->last_name.' '.$this->getItem()->first_name.' '.$this->getItem()->middle_name,
            ]);
        } else {
            //$this->indexPage()->setTitle($this->title.': '.$this->query()->count());
        }
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            //BelongsTo::make('Аккаунт', 'user', resource: new ApiPostUserResource()),
            //BelongsTo::make('ID поста', 'post', resource: new ApiChannelPostResource()),
            Text::make('Пользователь', 'username', fn($item) => $item->user->username),
            Text::make('Источник', 'source', fn($item) => $item?->post?->channel?->title),
            Text::make('Пост', 'post', fn($item) => Str::limit($item?->post?->post, 200)),
            Text::make('ИИ представление', 'ai_reason'),
            Date::make('Дата сообщения', 'post_date')->withTime()->sortable(),
            Enum::make('Статус', 'status')->attach(ApiPostAiStatusEnum::class)->sortable(),
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
            Text::make('Специализация', 'specialities', fn($item) => implode("; ", $item->specialtiesWithTitle())),
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
            Text::make('Контакты из сообщения', 'contact_info'),
            Enum::make('Статус', 'status')->attach(ApiPostAiStatusEnum::class),
            Date::make('Создан', 'created_at')->withTime(),

            HasOne::make('Сообщение', 'post', resource: new ApiChannelPostResource())->fields([
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

            HasOne::make('Аккаунт', 'user', resource: new ApiPostUserResource())->fields([
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
            $dictionaryArr[$row['id']] = trim($row['okso_code'].' '.$row['title']);
        }

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Date::make('Дата сообщения', 'post_date')->withTime()->disabled()->readonly();
        $fields[] = Select::make('Специальность', 'specialitiesForMoonshine')
            ->options($dictionaryArr)
            ->multiple()
            ->nullable()
            ->searchable();
        $fields[] = Textarea::make('Опыт работы по специальности', 'experience')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Владение ПО', 'soft_experience')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Образование', 'education')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Требуемый график работы', 'work_schedule')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Общая продолжительность работы - проекта', 'total_work_project')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Тип работы', 'type_of_work')->customAttributes(['rows' => '5']);
        $fields[] = Text::make('Желаемая оплата за час', 'price_by_hour');
        $fields[] = Text::make('Желаемая оплата общая сумма выплат за проект', 'price_by_project');
        $fields[] = Text::make('Желаемая оплата фиксированная оплата за период времени (месяц)', 'price_by_month');
        $fields[] = Textarea::make('О себе', 'about')->customAttributes(['rows' => '5']);
        $fields[] = Textarea::make('Спец. требования', 'spec_requirements')->customAttributes(['rows' => '5']);
        $fields[] = Text::make('Ссылка на резюме', 'link_resume');
        $fields[] = Textarea::make('Контакты из сообщения', 'contact_info');
        $fields[] = Enum::make('Статус', 'status')->attach(ApiPostAiStatusEnum::class);
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();

        return $fields;
    }
}
