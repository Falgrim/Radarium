<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Models\ApiChannelPost;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FormBuilder;
use MoonShine\Enums\ToastType;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Field;
use MoonShine\Fields\HiddenIds;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Select;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Http\Responses\MoonShineJsonResponse;
use MoonShine\MoonShineRequest;
use MoonShine\Resources\ModelResource;

/**
 * @extends ModelResource<ApiChannelPost>
 */
class ApiChannelPostResource extends ModelResource
{
    protected string $model = ApiChannelPost::class;

    protected string $title = 'Cообщения/Посты';

    protected string $sortColumn = 'post_date';

    protected string $sortDirection = 'DESC';

    protected string $column = 'post_id';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    protected bool $columnSelection = true;

    protected bool $saveFilterState = false;

    public function query(): Builder
    {
        $query = parent::query();

        $channelType = request()->input('filters.channel_type');
        if ($channelType !== null && $channelType !== '') {
            $query->whereHas('channel', fn ($q) => $q->where('is_company', (int) $channelType));
        }

        return $query;
    }

    public function getActiveActions(): array
    {
        return ['view', 'update', 'delete', 'massDelete'];
    }

    protected function modifyMassDeleteButton(ActionButton $button): ActionButton
    {
        return $button->setLabel('Удалить выбранные');
    }

    /**
     * @return list<ActionButton>
     */
    public function getIndexItemButtons(): array
    {
        return [
            ...parent::getIndexItemButtons(),
            $this->massUpdateAiParseStatusButton(),
        ];
    }

    /**
     * Массовая смена «Статус ИИ» для отмеченных строк таблицы (те же права, что и massDelete).
     */
    public function massUpdateAiParseStatus(MoonShineRequest $request): MoonShineJsonResponse
    {
        if (! $this->can('massDelete')) {
            return MoonShineJsonResponse::make()
                ->toast('Недостаточно прав для массовых операций', ToastType::ERROR);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:'.ApiChannelPost::table().',id'],
            'ai_parse_status' => ['required', Rule::enum(ApiChannelPostStatusEnum::class)],
        ]);

        $status = ApiChannelPostStatusEnum::from((int) $validated['ai_parse_status']);

        $updated = ApiChannelPost::query()
            ->whereIn('id', $validated['ids'])
            ->update([
                'ai_parse_status' => $status->value,
                'updated_at' => now(),
            ]);

        return MoonShineJsonResponse::make()
            ->toast(
                $updated > 0
                    ? "Статус ИИ обновлён у {$updated} сообщ."
                    : 'Записи не обновлены',
                $updated > 0 ? ToastType::SUCCESS : ToastType::WARNING
            )
            ->redirect(to_page(resource: self::class));
    }

    protected function massUpdateAiParseStatusButton(): ActionButton
    {
        $statusOptions = collect(ApiChannelPostStatusEnum::cases())
            ->mapWithKeys(fn (ApiChannelPostStatusEnum $case): array => [
                (string) $case->value => $case->toString() ?? (string) $case->value,
            ])
            ->all();

        return ActionButton::make('Статус ИИ', '#')
            ->bulk($this->listComponentName())
            ->icon('heroicons.arrow-path')
            ->showInLine()
            ->canSee(fn (): bool => $this->can('massDelete'))
            ->inOffCanvas(
                fn (): string => 'Статус ИИ для выбранных сообщений',
                fn () => FormBuilder::make()
                    ->name('mass-ai-parse-status-form')
                    ->fields([
                        HiddenIds::make($this->listComponentName()),
                        Select::make('Статус ИИ', 'ai_parse_status')
                            ->options($statusOptions)
                            ->required(),
                    ])
                    ->asyncMethod('massUpdateAiParseStatus', resource: $this)
                    ->submit('Применить'),
                isLeft: false,
            );
    }

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function export(): ?ExportHandler
    {
        return null;
    }

    protected function resolveOrder(): static
    {
        if (($sort = request('sort')) && is_string($sort)) {
            $column = ltrim($sort, '-');
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

            if ($column === 'api_channel_id') {
                $this->query()
                    ->select('api_channel_posts.*')
                    ->leftJoin('api_channels', 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
                    ->orderBy('api_channels.title', $direction);

                return $this;
            }
        }

        return parent::resolveOrder();
    }

    /**
     * @param  ApiChannelPost  $item
     * @return array<string, string[]|string>
     *
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'post' => ['required', 'string', 'min:10'],
            'ai_parse_status' => Rule::enum(ApiChannelPostStatusEnum::class),
        ];
    }

    public function search(): array
    {
        return ['post', 'id', 'user_login'];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Select::make('Источник', 'api_channel_id')
                ->options(
                    ['' => 'Все источники'] + \App\Models\ApiChannel::query()->pluck('title', 'id')->toArray()
                )
                ->searchable()->nullable(),
            Select::make('Тип выборки', 'channel_type')
                ->options(
                    ApiDataTypeEnum::getList()
                )
                ->nullable()
                ->onApply(fn (Builder $query, $value, Field $field) => $query),
            Text::make('API ID', 'post_id'),
            Text::make('ID аккаунта', 'api_post_user_id'),
            Text::make('Логин', 'user_login'),
            DateRange::make('Дата публикации', 'post_date')->withTime(),
            DateRange::make('Создан', 'created_at')->withTime(),
            Select::make('Статус', 'ai_parse_status')
                ->options(
                    ApiChannelPostStatusEnum::getList()
                )->nullable(),
            Select::make('Провайдер ИИ', 'ai_provider_used')
                ->options(
                    ApiAiSourceEnum::getList()
                )->nullable(),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            BelongsTo::make('Источник', 'channel', 'api_channel_id', resource: new ApiChannelResource)->setColumn('api_channel_id')->sortable(),
            Text::make('API ID', 'post_id')->sortable(),
            Text::make('Логин', 'user_login')->sortable(),
            Text::make('Сообщение', 'post', fn ($item) => Str::limit($item->post, 100)),
            Date::make('Дата публикации', 'post_date')->withTime()->sortable(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
            Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class)->sortable(),
            Text::make('Провайдер ИИ', 'ai_provider_used', function ($item) {
                if (! $item->ai_provider_used) {
                    return '';
                }
                $enum = ApiAiSourceEnum::tryFrom($item->ai_provider_used);
                if (! $enum) {
                    return $item->ai_provider_used;
                }
                $color = $enum->getColor();
                $label = $enum->toString();

                return "<span class=\"badge badge-{$color}\">{$label}</span>";
            })->sortable(),
        ];
    }

    public function detailFields(): array
    {
        if ($this->item->channel->is_company === ApiDataTypeEnum::Company) {
            $aiData = [
                HasOne::make('ИИ. Вакансия', 'companyJob', resource: new CompanyJobResource)->fields([
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
                ]),
            ];
        } else {
            $aiData = [
                HasOne::make('ИИ. Специалист (резюме)', 'specialist', resource: new SpecialistResource)->fields([
                    Text::make('ID', 'id'),
                    Text::make('Тип сообщения', 'ai_type'),
                    Text::make('Подробнее о типе', 'ai_reason'),
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
                    Enum::make('Статус', 'status')->attach(ApiPostAiStatusEnum::class),
                    Date::make('Создан', 'created_at')->withTime(),
                ]),
            ];
        }

        $result = [
            Text::make('ID', 'id'),
            Text::make('API ID', 'post_id'),
            Text::make('Логин', 'user_login'),
            Text::make('Сообщение', 'post'),
            Date::make('Дата публикации', 'post_date')->withTime(),
            Date::make('Создан', 'created_at')->withTime(),
            Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class),
            Text::make('Провайдер ИИ', 'ai_provider_used', function ($item) {
                if (! $item->ai_provider_used) {
                    return '—';
                }
                $enum = ApiAiSourceEnum::tryFrom($item->ai_provider_used);

                return $enum ? $enum->toString() : $item->ai_provider_used;
            }),
            Text::make('Ответ ИИ', 'ai_result'),
            Date::make('Запрос к ИИ', 'ai_date')->withTime(),
        ];

        $result = array_merge($result, $aiData);

        $result = array_merge($result, [
            HasOne::make('Аккаунт', 'apiPostUser', resource: new ApiPostUserResource)->fields([
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
        ]);

        return $result;
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('API ID', 'post_id')->disabled()->readonly();
        $fields[] = Text::make('Логин', 'user_login');
        $fields[] = Textarea::make('Сообщение', 'post')->customAttributes(['autocomplete' => 'off', 'rows' => '5']);
        $fields[] = Date::make('Дата публикации', 'post_date')->withTime()->disabled()->readonly();
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();
        $fields[] = Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class)
            ->hint('Вы можете сбросить параметр статуса, чтобы система повторно проверила сообщение.');

        return $fields;
    }
}
