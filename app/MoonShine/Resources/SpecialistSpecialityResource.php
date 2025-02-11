<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\SpecialistStatusEnum;
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
class SpecialistSpecialityResource extends ModelResource
{
    protected string $model = SpecialistSpeciality::class;

    protected string $title = 'Специальности специалистов';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'dictionarySpeciality';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;

    public function getActiveActions(): array
    {
        return ['view', 'delete', 'massDelete'];
    }

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function export(): ?ExportHandler
    {
        return null;
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
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
            HasOne::make('Специалист', 'specialist', resource: new SpecialistResource())->sortable(),
            HasOne::make('Специализация', 'dictionarySpeciality', resource: new DictionarySpecialityResource())->sortable(),

        ];
    }

    public function detailFields(): array
    {
        return [
            ID::make(),
            HasOne::make('Специалист', 'specialist', resource: new SpecialistResource())->fields([
                Text::make('ID', 'id'),
            ]),
            HasOne::make('Специализация', 'speciality', resource: new DictionarySpecialityResource())->fields([
                Text::make('Название', 'title'),
            ]),
            Date::make('Создан', 'created_at')->withTime(),
        ];
    }

    public function formFields(): array
    {
        $fields = [];
        return $fields;
    }
}
