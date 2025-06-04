<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\MailingMessageStatusEnum;
use App\Models\ApiPostUser;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use App\Services\ReadTelegramChats;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\CompanyJob;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\ComponentAttributeBag;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Decorations\Collapse;
use MoonShine\Fields\Checkbox;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Image;
use MoonShine\Fields\Markdown;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Select;
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
 * @extends ModelResource<ApiPostUser>
 */
class CompanyAuthorsResource extends ModelResource
{
    protected string $model = ApiPostUser::class;

    protected string $title = 'Работодатели';

    protected string $sortColumn = 'created_at';

    protected string $sortDirection = 'DESC';

    public string $column = 'username';

    protected bool $isAsync = false;

    protected bool $editInModal = false;

    protected bool $withPolicy = true;

    protected bool $stickyTable = true;


    public function query(): Builder
    {
        return parent::query()->where('is_company', ApiDataTypeEnum::Company);
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
        return ['view', 'update'];
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

    public function search(): array
    {
        return ['posts.post', 'username'];
    }

    public function filters(): array
    {
        return [
            Checkbox::make('Отпр. авторассылка', 'send_welcome_msg'),
            Checkbox::make('Руч. отправка', 'send_new_msg'),
        ];
    }

    public function indexFields(): array
    {
        return [
            ID::make()->sortable(),
            Image::make('Фото', 'photo')->disk('public')->dir(ReadTelegramChats::PHOTO_PATH),
            Text::make('Пользователь', 'username'),
            Text::make('Фамилия', 'last_name'),
            Text::make('Имя', 'first_name'),
            Enum::make('Отпр. авторассылка', 'send_welcome_msg')->attach(ApiPostUserMailingStatusEnum::class)->sortable(),
            Checkbox::make('Руч. отправка', 'send_new_msg')->updateOnPreview(),
            Date::make('Создан', 'created_at')->withTime()->sortable(),
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('ID', 'id'),
            Image::make('Фото', 'photo')->disk('public')->dir(ReadTelegramChats::PHOTO_PATH),
            Text::make('Пользователь', 'username'),
            Text::make('Фамилия', 'last_name'),
            Text::make('Имя', 'first_name'),
            Enum::make('Отпр. авторассылка', 'send_welcome_msg')->attach(ApiPostUserMailingStatusEnum::class),
            Checkbox::make('Руч. отправка', 'send_new_msg')->updateOnPreview(),

            HasMany::make('Сообщения', 'posts', resource: new ApiChannelPostResource())->fields([
                Text::make('ID', 'id'),
                Text::make('API ID', 'post_id'),
                Text::make('Источник', 'source', fn($item) => $item->channel->title),
                Text::make('Логин', 'user_login'),
                Text::make('Сообщение', 'post'),
                Date::make('Дата публикации', 'post_date')->withTime(),
                Date::make('Создан', 'created_at')->withTime(),
                Enum::make('Статус ИИ', 'ai_parse_status')->attach(ApiChannelPostStatusEnum::class),
                Text::make('Ответ ИИ', 'ai_result'),
                Date::make('Запрос к ИИ', 'ai_date')->withTime()
            ]),
        ];
    }

    public function rules(Model $item): array
    {
        return [
            'send_new_msg' => ['sometimes', 'integer'],
        ];
    }

    public function formFields(): array
    {
        $fields = [];
        $fields[] = Text::make('ID', 'id')->disabled()->readonly();
        $fields[] = Image::make('Фото', 'photo')->disk('public')->dir(ReadTelegramChats::PHOTO_PATH)->disabled()->readonly();
        $fields[] = Text::make('Пользователь', 'username')->disabled()->readonly();
        $fields[] = Text::make('Фамилия', 'last_name')->disabled()->readonly();
        $fields[] = Text::make('Имя', 'first_name')->disabled()->readonly();
        $fields[] = Checkbox::make('Авторассылка', 'is_main')
            ->onValue(1)
            ->offValue(0)
            ->hint('Автоматическое сообщение для новых компаний, может быть только одно')->disabled()->readonly();
        $fields[] = Checkbox::make('Руч. отправка', 'send_new_msg');
        $fields[] = Date::make('Создан', 'created_at')->withTime()->disabled()->readonly();
        return $fields;
    }
}
