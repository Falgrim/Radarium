<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Http\Controllers\ConfigurationController;
use Illuminate\Database\Eloquent\Model;
use App\Models\Configuration;

use Illuminate\View\ComponentAttributeBag;
use MoonShine\Fields\Preview;
use MoonShine\Fields\Select;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Td;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<Configuration>
 */
class ConfigurationResource extends ModelResource
{
    protected bool $saveFilterState = true;

    protected string $model = Configuration::class;

    protected string $title = 'Настройки';

    protected string $sortColumn = 'order';

    protected string $sortDirection = 'ASC';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    public function __construct(
        protected ConfigurationController $configurationController = new ConfigurationController
    ) {}

    public function search(): array
    {
        return [];
    }

    public function import(): ?ImportHandler
    {
        return null;
    }

    public function export(): ?ExportHandler
    {
        return null;
    }

    public function getActiveActions(): array
    {
        return ['update'];
    }

    public function indexFields(): array
    {
        return [
            Text::make('Ключ', 'name'),
            Preview::make('Название', 'title', static fn($item) => $item->title_hint ? ($item->title."<br /><small>".$item->title_hint."</small>") : $item->title),
            Td::make('Значение', function (Configuration $v) {
                $value = $this->configurationController->valueByType($v->type, $v->options, $v->value);
                if ($v->type == 'checkbox') {
                    $value = $value ? 'Да/Вкл.' : 'Нет/Выкл.';
                }
                return [
                    Text::make('Значение')->setValue($value)->badge(fn($status, Field $field) => 'info') ,
                ];
            }),
        ];
    }

    protected function onBoot(): void
    {
        if (!is_null($this->getItem())) {
            $this->formPage()
                ->setBreadcrumbs([
                    $this->indexPage()->url() => $this->title(),
                    '#' => $this->getItem()->title,
                ]);
        }
    }

    public function formFields(): array
    {
        $fields = [];

        $fields[] = Preview::make('', 'title', static fn($item) => '<b>'.$item->title.'</b>');

        if ($this->item->title_hint) {
            $fields[] = Preview::make('', 'title_hint', static fn($item) => '<small>'.$item->title_hint.'</small>');
        }

        $type = $this->getItem()->type;

        if ($type == 'select') {
            $fields[] = Select::make('Значение', 'value')
                ->options(unserialize($this->item->getAttribute('options')));
        } elseif ($type == 'multi_select') {
            $fields[] = Select::make('Значение', 'value')
                ->options(unserialize($this->item->getAttribute('options')))
                ->multiple();
        } elseif ($type == 'textarea') {
            $fields[] = Textarea::make('Значение', 'value')
                ->customAttributes(['rows' => '20']);
        } elseif ($type == 'checkbox') {
            $fields[] = Switcher::make('Да/Включено', 'value');
        } else {
            $fields[] = Text::make(
                'Значение',
                'value',
            );
        }

        return $fields;
    }

    /**
     * @return list<MoonShineComponent|Field>
     */
    public function fields(): array
    {
        return [
            Block::make([
                Text::make('Ключ', 'name'),
                Text::make('Название', 'title'),
                Text::make('Тип', 'type'),
            ]),
        ];
    }

    /**
     * @param Configuration $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [];
    }
}
