<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiChannelPost;
use App\MoonShine\Pages\ApiChannelPost\ApiChannelPostIndexPage;
use App\MoonShine\Pages\ApiChannelPost\ApiChannelPostFormPage;
use App\MoonShine\Pages\ApiChannelPost\ApiChannelPostDetailPage;

use MoonShine\Fields\Date;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Text;
use MoonShine\Resources\ModelResource;
use MoonShine\Pages\Page;

/**
 * @extends ModelResource<ApiChannelPost>
 */
class ApiChannelPostResource extends ModelResource
{
    protected string $model = ApiChannelPost::class;

    protected string $title = 'Лог обработки сообщений';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'post_id';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    public function getActiveActions(): array
    {
        return ['view', 'update', 'delete', 'massDelete'];
    }

    /**
     * @return list<Page>
     */
    public function pages(): array
    {
        return [
            ApiChannelPostIndexPage::make($this->title()),
            ApiChannelPostFormPage::make(
                $this->getItemID()
                    ? __('moonshine::ui.edit')
                    : __('moonshine::ui.add')
            ),
            ApiChannelPostDetailPage::make(__('moonshine::ui.show')),
        ];
    }

    /**
     * @param ApiChannelPost $item
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
            Text::make('ID', 'id'),
            Text::make('API ID', 'post_id'),
            Text::make('Логин', 'user_login'),
            Text::make('Сообщение', 'post', fn($item) => mb_substr($item->post, 0, 50).'...'),
            Date::make('Дата', 'post_date'),
            Text::make('Создано', 'created_at'),
            Text::make('Статус ИИ', 'ai_result_status'),
        ];
    }
}
