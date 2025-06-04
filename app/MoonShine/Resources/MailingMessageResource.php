<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\MailingMessageStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\MailingMessage;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\ComponentAttributeBag;
use MoonShine\Decorations\Grid;
use MoonShine\Fields\Checkbox;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Image;
use MoonShine\Fields\Markdown;
use MoonShine\Fields\Preview;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Metrics\ValueMetric;
use MoonShine\QueryTags\QueryTag;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<ApiPostUser>
 */
class MailingMessageResource extends ModelResource
{
    protected string $model = MailingMessage::class;

    protected string $title = 'Рассылка';

    protected string $sortColumn = 'date_send';

    protected string $sortDirection = 'DESC';

    public string $column = 'date_send';

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
        return ['view', 'create', 'delete', 'update'];
    }

    /**
     * @return Field
     */
    public function fields(): array
    {
        return [
            Block::make([
                ID::make()->sortable(),
            ]),
        ];
    }

    public function search(): array
    {
        return ['text'];
    }

    public function filters(): array
    {
        return [];
    }

    public function queryTags(): array
    {
        return [
            QueryTag::make(
                'Авторассылка', // Заголовок тега
                fn(Builder $query) => $query->where('is_main', 1)
            ),
            QueryTag::make(
                'Ручная рассылка', // Заголовок тега
                fn(Builder $query) => $query->where('is_main', 0)
            )
        ];
    }

    public function trAttributes(): Closure
    {
        return function (
            Model $item,
            int $row,
            ComponentAttributeBag $attr
        ): ComponentAttributeBag {
            if ($item->is_main) {
                $attr->setAttributes([
                    'class' => 'bgc-blue'
                ]);
            }

            return $attr;
        };
    }

    public function metrics(): array
    {
        return [
            ValueMetric::make('Ожидаю отправки')
                ->value(MailingMessage::where('status', MailingMessageStatusEnum::ToSend)->where('date_send', '>=', Carbon::now())->count())
                ->columnSpan(6),
            ValueMetric::make('Выбрано компаний')
                ->value(ApiPostUser::where('is_company', 1)->where('send_new_msg', 1)->count())
                ->columnSpan(6),
        ];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Текст сообщения', 'text', fn($item) => Str::limit($item->text, 150)),
            Date::make('Дата отправки', 'date_send', fn($item) => $item->is_main ? Carbon::now()->format("Y-m-d H:i") : $item->date_send)->withTime(),
            Enum::make('Статус', 'status', fn($item) => $item->is_main ? '---' : $item->status)->attach(MailingMessageStatusEnum::class),
            HasMany::make('Сообщения', 'posts', resource: new MailingMessageLogResource())->onlyLink(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            Preview::make('Текст сообщения', 'text'),
            Date::make('Дата отправки', 'date_send')->withTime(),
            Enum::make('Статус', 'status')->attach(MailingMessageStatusEnum::class),
            Checkbox::make('Авторассылка', 'is_main'),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
            HasMany::make('Сообщения', 'posts', resource: new MailingMessageLogResource()),
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
            'text' => ['required', 'string', 'min:10'],
            'date_send' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'status' => Rule::enum(MailingMessageStatusEnum::class),
            'is_main' => ['sometimes', 'integer'],
        ];
    }

    public function formFields(): array
    {
        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Textarea::make('Текст сообщения', 'text')->customAttributes(['autocomplete' => 'off', 'rows' => '5']);
        $fields[] = Date::make('Дата отправки', 'date_send')->withTime();
        $fields[] = Enum::make('Статус', 'status')->attach(MailingMessageStatusEnum::class);
        $fields[] = Checkbox::make('Авторассылка', 'is_main')
            ->onValue(1)
            ->offValue(0)
            ->hint('Автоматическое сообщение для новых компаний, может быть только одно. На данный тип сообщения не влияет дата отправки и статусы.');
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();

        return $fields;
    }

    protected function afterCreated(Model $item): Model
    {
        if (isset($item->is_main) AND $item->is_main) {
            MailingMessage::where('is_main', 1)->where('id', '<>', $item->id)->update(['is_main' => 0]);
        }

        return $item;
    }

    protected function afterUpdated(Model $item): Model
    {
        if (isset($item->is_main) AND $item->is_main) {
            MailingMessage::where('is_main', 1)->where('id', '<>', $item->id)->update(['is_main' => 0]);
        }

        return $item;
    }
}
