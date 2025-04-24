<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\UserTariffStatusEnum;
use App\Models\UserTariff;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;
use MoonShine\Fields\Date;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Number;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Relationships\HasOne;
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
class UserTariffResource extends ModelResource
{
    protected string $model = UserTariff::class;

    protected string $title = 'Подписки';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'paymentTariff.title';

    protected bool $isAsync = false;

    protected bool $editInModal = true;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

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
            Select::make('Статус', 'status')->options(UserTariffStatusEnum::getList()),
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

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Пользователь', 'user.email')->sortable(),
            Text::make('Тариф', 'paymentTariff.title'),
            Enum::make('Статус', 'status')->attach(UserTariffStatusEnum::class)->sortable(),
            Text::make('Кол-во дней.', 'period')->sortable(),
            Text::make('Контактов', 'count_contacts')->sortable(),
            Text::make('Осталось к.', 'count_contacts_left')->sortable(),
            Date::make('Начало', 'date_start')->withTime()->sortable(),
            Date::make('Завершение', 'date_end')->withTime()->sortable(),
            Date::make('Комментарий', 'comment')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            ID::make(),
            HasOne::make('Пользователь', 'user', resource: new UserResource())->fields([
                Text::make('Имя', 'name'),
                Email::make('Почта', 'email'),
            ]),
            Text::make('Тариф', 'paymentTariff.title'),
            Enum::make('Статус', 'status')->attach(UserTariffStatusEnum::class)->sortable(),
            Text::make('Кол-во дней', 'period')->sortable(),
            Text::make('Контактов', 'count_contacts')->sortable(),
            Text::make('Осталось к.', 'count_contacts_left')->sortable(),
            Date::make('Начало', 'date_start')->withTime()->sortable(),
            Date::make('Завершение', 'date_end')->withTime()->sortable(),
            Date::make('Комментарий', 'comment')->withTime()->sortable(),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        //$fields[] = Email::make('Пользователь', 'user_email', fn($item) => $item->user?->email);
        //$fields[] = BelongsTo::make('Пользователь', 'user', resource: new UserResource());
        $fields[] = BelongsTo::make('Пользователь', 'user', resource: new UserResource())->asyncSearch();
        $fields[] = BelongsTo::make('Тариф', 'paymentTariff', resource: new PaymentTariffResource());
        $fields[] = Number::make('Кол-во дней', 'period')->default(30);
        $fields[] = Number::make('Контактов', 'count_contacts')->default(100);
        $fields[] = Number::make('Осталось к.', 'count_contacts_left')->default(100);
        $fields[] = Date::make('Начало', 'date_start')->default(Carbon::now()->format('Y-m-d H:i'))->withTime();
        $fields[] = Date::make('Завершение', 'date_end')->default(Carbon::now()->addMonths(2)->format('Y-m-d H:i'))->withTime();
        $fields[] = Text::make('Комментарий', 'comment');
        $fields[] = Enum::make('Статус', 'status')->attach(UserTariffStatusEnum::class)->default(UserTariffStatusEnum::Active);

        return $fields;
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
            'user_id' => ['required', 'exists:App\Models\User,id'],
            'payment_tariff_id' => ['required', 'exists:App\Models\PaymentTariff,id'],
            'period' => ['required', 'digits_between:0,100000'],
            'count_contacts' => ['required', 'digits_between:0,100000'],
            'count_contacts_left' => ['required', 'digits_between:0,100000'],
            'date_start' => ['required', 'date_format:Y-m-d\TH:i'],
            'date_end' => ['required', 'date_format:Y-m-d\TH:i'],
            'comment' => ['sometimes', 'nullable', 'string'],
            'status' => Rule::enum(UserTariffStatusEnum::class),
        ];
    }

    public function redirectAfterSave(): string
    {
        return to_page(resource: UserTariffResource::class);
    }
}
