<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ReviewCustomField;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;

use Illuminate\Support\Str;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Number;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Fields\TinyMce;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<Review>
 */
class ReviewResource extends ModelResource
{
    protected string $model = Review::class;

    protected string $title = 'Отзывы (резюме)';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'created_at';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    public function getActiveActions(): array
    {
        return ['view', 'update', 'delete', 'massDelete'];
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
        return ['text'];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Пользователь ID', 'user_id'),
            //Text::make('Специалист ID', 'specialist_id'),
            Enum::make('Возможн. ред.', 'can_edit')->attach(ReviewCanEditEnum::class),
            Number::make('Оценка', 'rating')->hint('От 0 до 5')->min(0)->max(5),
            Enum::make('Статус', 'status')->attach(ReviewStatusEnum::class),
            DateRange::make('Создан', 'created_at')->withTime(),
        ];
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
     * @param Review $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'text' => ['required', 'string', 'min:10'],
            'rating' => ['required', 'digits_between:0,5'],
            'can_edit' => Rule::enum(ReviewCanEditEnum::class),
            'status' => Rule::enum(ReviewStatusEnum::class),
        ];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            HasOne::make('Пользователь', 'user', resource: new UserResource())->fields([
                Text::make('ID', 'id'),
                Text::make('Имя', 'name'),
                Email::make('Почта', 'email'),
            ]),
            /*HasOne::make('Специалист', 'specialist', resource: new SpecialistResource())->fields([
                //Text::make('ID', 'id'),
                Text::make('О себе', 'about', fn($item) => Str::limit($item->about, 100)),
                //Enum::make('Статус', 'status')->attach(SpecialistStatusEnum::class),
                Date::make('Создан', 'created_at')->withTime(),
            ]),*/
            Text::make('Отзыв', 'text', fn($item) => Str::limit($item->about, 100)),
            Enum::make('Возможн. ред.', 'can_edit')->attach(ReviewCanEditEnum::class),
            Number::make('Оценка', 'rating')->hint('От 0 до 5')->min(0)->max(5)->stars(),
            Enum::make('Статус', 'status')->attach(ReviewStatusEnum::class)->sortable(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            ID::make(),
            HasOne::make('Пользователь', 'user', resource: new UserResource())->fields([
                Text::make('ID', 'id'),
                Text::make('Имя', 'name'),
                Email::make('Почта', 'email'),
            ]),
            /*HasOne::make('Специалист', 'specialist', resource: new SpecialistResource())->fields([
                Text::make('ID', 'id'),
                Text::make('О себе', 'about'),
                Enum::make('Статус', 'status')->attach(SpecialistStatusEnum::class),
                Date::make('Создан', 'created_at')->withTime(),
            ]),*/
            Text::make('Отзыв', 'text'),
            Text::make('Доп. поле', 'extra_field', function () {
                $extra = [];
                if ($this->item->reviewCustomFields->count()) {
                    foreach ($this->item->reviewCustomFields as $reviewCustomField) {
                        $extra[] = $reviewCustomField['title'].': '.$reviewCustomField['value'];
                    }
                }

                return implode('; ', $extra);
            }),
            Enum::make('Возможность редактирования', 'can_edit')->attach(ReviewCanEditEnum::class),
            Number::make('Оценка', 'rating')->hint('От 0 до 5')->min(0)->max(5)->stars(),
            Enum::make('Статус', 'status')->attach(ReviewStatusEnum::class),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Пользователь ID', 'user_id')->disabled()->readonly();
        $fields[] = TinyMce::make('Отзыв', 'text')
            ->menubar('')
            ->toolbar('undo redo | bold italic underline strikethrough | numlist bullist');

        if ($this->item->reviewCustomFields->count()) {
            $fields[] = HasMany::make('Доп. поле', 'reviewCustomFields', resource: new ReviewCustomFieldResource())->async();
        }

        $fields[] = Enum::make('Возможность редактирования', 'can_edit')->attach(ReviewCanEditEnum::class);
        $fields[] = Number::make('Оценка', 'rating')->hint('От 0 до 5')->min(0)->max(5)->stars();
        $fields[] = Enum::make('Статус', 'status')->attach(ReviewStatusEnum::class);
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();

        return $fields;
    }
}
