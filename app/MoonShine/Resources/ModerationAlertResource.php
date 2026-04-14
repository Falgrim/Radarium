<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Enum\ReviewCanEditEnum;
use App\Enum\ReviewStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ModerationAlert;
use App\Models\ReviewCustomField;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;

use Illuminate\Support\Str;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Number;
use MoonShine\Fields\Preview;
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
class ModerationAlertResource extends ModelResource
{
    protected bool $saveFilterState = false;

    protected string $model = ModerationAlert::class;

    protected string $title = 'Модерация';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'created_at';

    protected bool $isAsync = true;

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
        return [];
    }

    public function filters(): array
    {
        return [
            Text::make('ID', 'id'),
            Text::make('Пользователь ID', 'user_id'),
            Enum::make('Создатель', 'is_system')->attach(ModerationAlertSystemEnum::class)->nullable(),
            Enum::make('Раздел', 'table_name')->attach(ModerationAlertTableNameEnum::class)->nullable(),
            Text::make('ID записи', 'table_row_id'),
            Enum::make('Статус', 'status')->attach(ModerationAlertStatusEnum::class)->nullable(),
            DateRange::make('Создано', 'created_at')->withTime(),
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
            'description' => ['nullable', 'string', 'min:5', 'max:255'],
            'comment' => ['nullable', 'string', 'min:2'],
            'status' => Rule::enum(ModerationAlertStatusEnum::class),
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
            Enum::make('Создатель', 'is_system')->attach(ModerationAlertSystemEnum::class),
            Enum::make('Раздел', 'table_name')->attach(ModerationAlertTableNameEnum::class),
            Text::make('ID записи', 'table_row_id'),
            Enum::make('Статус', 'status')->attach(ModerationAlertStatusEnum::class),
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
            Enum::make('Создатель', 'is_system')->attach(ModerationAlertSystemEnum::class),
            Enum::make('Раздел', 'table_name')->attach(ModerationAlertTableNameEnum::class),
            Preview::make('Ссылка', 'link', static function ($item) {
                $params = $item->getObject();
                if (is_null($params)) {
                    return 'Запись не обнаружена...';
                }

                $className = '\\App\\MoonShine\\Resources\\'.$item->table_name->value.'Resource';
                $page = (new $className)->detailPageUrl($item->table_row_id);
                return ActionButton::make('Открыть', $page)->blank()->primary();
            }),
            Text::make('Комментарий от автора', 'description'),
            Text::make('Комментарий модератора', 'comment'),
            Enum::make('Статус', 'status')->attach(ModerationAlertStatusEnum::class),
        ];
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Text::make('ID', 'id')->disabled();
        $fields[] = Text::make('Пользователь ID', 'user_id')->disabled();
        $fields[] = Enum::make('Создатель', 'is_system')->attach(ModerationAlertSystemEnum::class)->disabled();
        $fields[] = Enum::make('Раздел', 'table_name')->attach(ModerationAlertTableNameEnum::class)->disabled();
        $fields[] = Text::make('ID записи', 'table_row_id')->disabled();
        $fields[] = TinyMce::make('Комментарий от автора', 'description')
                ->menubar('')
                ->toolbar('undo redo | bold italic underline strikethrough | numlist bullist');
        $fields[] = TinyMce::make('Комментарий модератора', 'comment')
                ->menubar('')
                ->toolbar('undo redo | bold italic underline strikethrough | numlist bullist');
        $fields[] = Enum::make('Статус', 'status')->attach(ModerationAlertStatusEnum::class);
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled();

        return $fields;
    }
}
