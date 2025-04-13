<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Password;
use Illuminate\Validation\Rules\Password as ValidPassword;
use MoonShine\Fields\PasswordRepeat;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Select;
use MoonShine\Fields\Text;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * @extends ModelResource<User>
 */
class UserResource extends ModelResource
{
    protected string $model = User::class;

    protected string $title = 'Пользователи';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    protected string $column = 'email';

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

    public function prepareForValidation(): void
    {
        request()?->merge([
            'phone' => (string) new PhoneNumber(request()?->string('phone')->value(), 'RU'),
        ]);
    }

    /**
     * @param User $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'phone:mobile,RU', 'unique:'.User::class.',phone,'.$item->id],
            'company_inn' => ['sometimes', 'nullable', 'integer', 'digits_between:10,12'],
            'company_title' => ['sometimes', 'nullable', 'string', 'min:3', 'max:100'],
            'user_role_id' => ['exists:App\Models\UserRole,id'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $item->exists ? Rule::unique('users')->ignore($item->id) : 'unique:'.User::class],
            'password' => $item->exists ? ['sometimes', 'nullable', ValidPassword::min(6)] : ['required', ValidPassword::min(6)],
            'email_verified_at' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i']
        ];
    }

    public function search(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Email::make('Почта', 'email'),
            Text::make('Телефон', 'phone'),
            DateRange::make('Регистрация', 'created_at')->withTime(),
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            BelongsTo::make('Роль', 'userRole', resource: new UserRoleResource())->badge('purple')->sortable(),
            Text::make('Имя', 'name')->sortable(),
            Text::make('Телефон', 'phone')->sortable(),
            Email::make('Почта', 'email')->sortable(),
            Email::make('Tubus ID', 'tubus_id')->sortable(),
            Date::make('Регистрация', 'created_at')->withTime()->sortable(),
            Date::make('Подтвержден', 'email_verified_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            BelongsTo::make('Роль', 'userRole', resource: new UserRoleResource())->badge('purple')->sortable(),
            Text::make('Имя', 'name'),
            Text::make('Телефон', 'phone'),
            Text::make('ИНН компании', 'company_inn'),
            Text::make('Название компании', 'company_title'),
            Email::make('Почта', 'email'),
            Email::make('Tubus ID', 'tubus_id'),
            Date::make('Регистрация', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Tubus ID', 'tubus_id')->disabled()->readonly();
        $fields[] = BelongsTo::make('Роль', 'userRole');
        $fields[] = Text::make('Имя', 'name');
        $fields[] = Text::make('Телефон', 'phone');
        $fields[] = Text::make('ИНН компании', 'company_inn')->hint('От 10 до 12 цифр');
        $fields[] = Text::make('Название компании', 'company_title');
        $fields[] = Email::make('Почта', 'email');
        $fields[] = Date::make('Подтвержден', 'email_verified_at')->withTime();
        $fields[] = Password::make('Новый пароль', 'password')
            ->customAttributes(['autocomplete' => 'new-password'])
            ->hint('Минимум 6 символов')
            ->hideOnDetail()
            ->eye();
        $fields[] = Date::make('Регистрация', 'created_at')->withTime()->disabled()->readonly();

        return $fields;
    }
}
