<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\SpecialistStatusEnum;
use App\Models\DictionarySpeciality;
use App\Models\SpecialistSpeciality;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;

use Illuminate\Support\Str;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Number;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Text;
use MoonShine\Fields\TinyMce;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<Review>
 */
class DictionarySpecialityResource extends ModelResource
{
    protected string $model = DictionarySpeciality::class;

    protected string $title = 'Специальности';

    protected string $sortColumn = 'title';

    protected string $sortDirection = 'ASC';

    public string $column = 'title';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function filters(): array
    {
        return [
            Text::make('Название', 'title'),
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
        return [];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Специальность', 'title')->sortable(),
            Text::make('Aббревиатура', 'short_name')->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            ID::make(),
            Text::make('Специальность', 'title'),
            Text::make('Aббревиатура', 'short_name'),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Text::make('Специальность', 'title');
        $fields[] = Text::make('Aббревиатура', 'short_name');
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();
        return $fields;
    }
}
