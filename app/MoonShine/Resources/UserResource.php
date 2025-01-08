<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelSourceEnum;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

use MoonShine\Fields\Date;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Password;
use MoonShine\Fields\PasswordRepeat;
use MoonShine\Fields\Relationships\BelongsTo;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Text;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;

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
     * @param User $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'phone:mobile,RU'],
            'user_role_id' => ['exists:App\Models\UserRole,id'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $item->exists ? Rule::unique('users')->ignore($item->id) : 'unique:'.User::class],
            'password' => $item->exists ? ['sometimes', 'nullable', 'min:6'] : ['required', 'min:6'],
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
            Date::make('Регистрация', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            BelongsTo::make('Роль', 'userRole', resource: new UserRoleResource())->badge('purple')->sortable(),
            Text::make('Имя', 'name'),
            Text::make('Телефон', 'phone'),
            Email::make('Почта', 'email'),
            Date::make('Регистрация', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = BelongsTo::make('Роль', 'userRole');
        $fields[] = Text::make('Имя', 'name');
        $fields[] = Text::make('Телефон', 'phone');
        $fields[] = Email::make('Почта', 'email');
        $fields[] = Password::make('Новый пароль', 'password')
            ->customAttributes(['autocomplete' => 'new-password'])
            ->hint('Минимум 6 символов')
            ->hideOnDetail()
            ->eye();
        $fields[] = Date::make('Регистрация', 'created_at')->withTime()->disabled()->readonly();

        return $fields;
    }
}
