<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\PaymentTariffStatusEnum;
use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Models\PaymentTariff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FormBuilder;
use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Select;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Text;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\QueryTags\QueryTag;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;

/**
 * @extends ModelResource<Review>
 */
class PaymentTariffResource extends ModelResource
{
    protected string $model = PaymentTariff::class;

    protected string $title = 'Управление тарифами';

    protected string $sortColumn = 'status';

    protected string $sortDirection = 'ASC';

    public string $column = 'title';

    protected bool $isAsync = false;

    protected bool $editInModal = true;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    protected bool $usePagination = false;

    public function getActiveActions(): array
    {
        return ['view', 'create', 'update', 'delete'];
    }

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function export(): ?ExportHandler
    {
        return null;
    }

    public function search(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Название', 'title'),
            Enum::make('Статус', 'status')->attach(PaymentTariffStatusEnum::class),
        ];
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

    public function redirectAfterSave(): string
    {
        return to_page(resource: PaymentTariffResource::class);
    }

    public function queryTags(): array
    {
        return [
            QueryTag::make(
                'Активные',
                fn(Builder $query) => $query->where('status', PaymentTariffStatusEnum::Active) // Query builder
            ),
            QueryTag::make(
                'Архив',
                fn(Builder $query) => $query->where('status', PaymentTariffStatusEnum::Archive) // Query builder
            ),
            QueryTag::make(
                'Черновик',
                fn(Builder $query) => $query->where('status', PaymentTariffStatusEnum::Draft) // Query builder
            ),
            QueryTag::make(
                'Отключенные',
                fn(Builder $query) => $query->where('status', PaymentTariffStatusEnum::Disabled) // Query builder
            ),
        ];
    }

    /**
     * @param Review $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'title' => ['required', 'string', 'min:2'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['required', 'digits_between:0,1000000'],
            'period' => ['required', 'digits_between:0,10000'],
            'count_contacts' => ['required', 'digits_between:0,10000'],
            'status' => Rule::enum(PaymentTariffStatusEnum::class),
        ];
    }

    public function indexFields(): array
    {
        return [
            //ID::make()->sortable(),
            Text::make('Название', 'title')->sortable(),
            Text::make('Описание', 'description'),
            Enum::make('Статус', 'status')->attach(PaymentTariffStatusEnum::class)->sortable(),
            Text::make('Стоимость', 'price', fn($item) => number_format($item->price, 0, '.', ' '))->sortable(),
            Text::make('Период действия (дн.)', 'period')->sortable(),
            Text::make('Контактов', 'count_contacts')->sortable(),
            Text::make('За контакт', 'price_by_contact', fn($item) => $item->price ? number_format(ceil($item->price/$item->count_contacts), 0, '.', ' ') : 0)->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            ID::make(),
            Text::make('Название', 'title'),
            Text::make('Описание', 'description'),
            Enum::make('Статус', 'status')->attach(PaymentTariffStatusEnum::class),
            Text::make('Стоимость', 'price', fn($item) => number_format($item->price, 0, '.', ' ')),
            Text::make('Период действия (дн.)', 'period'),
            Text::make('Контактов', 'count_contacts'),
            Text::make('За контакт', 'price_by_contact', fn($item) => $item->price ? number_format(ceil($item->price/$item->count_contacts), 0, '.', ' ') : 0),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Название', 'title');
        $fields[] = Text::make('Описание', 'description');
        $fields[] = Text::make('Стоимость', 'price');
        $fields[] = Text::make('Период действия (дн.)', 'period');
        $fields[] = Text::make('Контактов', 'count_contacts');
        $fields[] = Switcher::make('Акция', 'is_hot')->default(0);
        $fields[] = Enum::make('Статус', 'status')->attach(PaymentTariffStatusEnum::class)->default(PaymentTariffStatusEnum::Archive);

        return $fields;
    }
}
