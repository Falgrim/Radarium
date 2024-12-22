<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelSourceEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

use MoonShine\Fields\Date;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Password;
use MoonShine\Fields\PasswordRepeat;
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $item->exists ? Rule::unique('users')->ignore($item->id) : 'unique:'.User::class],
            'password' => $item->exists ? ['sometimes', 'nullable', 'min:6'] : ['required', 'min:6'],
        ];
    }

    public function indexFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Text::make('Имя', 'name')->sortable(),
            Email::make('Почта', 'email')->sortable(),
            Date::make('Регистрация', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id')->sortable(),
            Text::make('Имя', 'name')->sortable(),
            Email::make('Почта', 'email')->sortable(),
            Date::make('Регистрация', 'created_at')->withTime()->sortable(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Имя', 'name');
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
