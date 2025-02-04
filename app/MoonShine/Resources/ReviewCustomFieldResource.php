<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\SpecialistStatusEnum;
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
class ReviewCustomFieldResource extends ModelResource
{
    protected string $model = ReviewCustomField::class;

    protected string $title = 'Доп. поля';

    protected string $sortColumn = 'review_id';

    protected string $sortDirection = 'DESC';

    public string $column = 'review_id';

    protected bool $isAsync = true;

    protected bool $editInModal = true;

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
            Text::make('Отзыв ID', 'review_id')->nullable(),
            Text::make('Заголовок', 'title')->nullable(),
            Text::make('Значение', 'value')->nullable(),
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
            'value' => ['required', 'string', 'min:2'],
        ];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Отзыв ID', 'review_id')->sortable(),
            Text::make('Заголовок', 'title')->sortable(),
            Text::make('Значение', 'value')->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            ID::make(),
            Text::make('Отзыв ID', 'review_id'),
            Text::make('Заголовок', 'title'),
            Text::make('Значение', 'value'),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Отзыв ID', 'review_id')->disabled()->readonly();
        $fields[] = Text::make('Заголовок', 'title');
        $fields[] = Text::make('Значение', 'value');

        return $fields;
    }
}
