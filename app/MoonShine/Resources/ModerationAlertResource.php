<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Models\ApiChannelPost;
use App\Models\ModerationAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FormBuilder;
use MoonShine\Decorations\Block;
use MoonShine\Enums\ToastType;
use MoonShine\Fields\Date;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\Email;
use MoonShine\Fields\Enum;
use MoonShine\Fields\Field;
use MoonShine\Fields\ID;
use MoonShine\Fields\Preview;
use MoonShine\Fields\Relationships\HasOne;
use MoonShine\Fields\Select;
use MoonShine\Fields\Text;
use MoonShine\Fields\TinyMce;
use MoonShine\Handlers\ExportHandler;
use MoonShine\Handlers\ImportHandler;
use MoonShine\Http\Responses\MoonShineJsonResponse;
use MoonShine\MoonShineRequest;
use MoonShine\Resources\ModelResource;

/**
 * @extends ModelResource<ModerationAlert>
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

    /**
     * @return list<ActionButton>
     */
    public function detailButtons(): array
    {
        return [
            $this->authorPostAiStatusButton(),
        ];
    }

    /**
     * @return list<ActionButton>
     */
    public function formButtons(): array
    {
        return [
            $this->authorPostAiStatusButton(),
        ];
    }

    /**
     * Смена «Статус ИИ» у сообщения автора без перехода в раздел «Сообщения/Посты»
     * (кнопка «Сообщить об ошибке» создаёт запись с типом ApiPostUser в поле «Раздел»).
     */
    public function updateAuthorPostAiParseStatus(MoonShineRequest $request): MoonShineJsonResponse
    {
        if (! $this->can('update')) {
            return MoonShineJsonResponse::make()
                ->toast('Недостаточно прав для изменения', ToastType::ERROR);
        }

        $alert = $this->getItem();
        if (! $alert instanceof ModerationAlert || $alert->table_name !== ModerationAlertTableNameEnum::Author) {
            return MoonShineJsonResponse::make()
                ->toast('Доступно только для обращений по автору сообщений', ToastType::ERROR);
        }

        $validated = $request->validate([
            'api_channel_post_id' => ['required', 'integer', 'exists:api_channel_posts,id'],
            'ai_parse_status' => ['required', Rule::enum(ApiChannelPostStatusEnum::class)],
        ]);

        $post = ApiChannelPost::query()->findOrFail($validated['api_channel_post_id']);
        if ((int) $post->api_post_user_id !== (int) $alert->table_row_id) {
            return MoonShineJsonResponse::make()
                ->toast('Сообщение не принадлежит автору из этого обращения', ToastType::ERROR);
        }

        $post->ai_parse_status = ApiChannelPostStatusEnum::from((int) $validated['ai_parse_status']);
        $post->save();

        return MoonShineJsonResponse::make()
            ->toast('Статус ИИ сообщения обновлён', ToastType::SUCCESS)
            ->redirect($this->formPageUrl($alert));
    }

    protected function authorPostAiStatusButton(): ActionButton
    {
        $statusOptions = collect(ApiChannelPostStatusEnum::cases())
            ->mapWithKeys(fn (ApiChannelPostStatusEnum $case): array => [
                (string) $case->value => $case->toString() ?? (string) $case->value,
            ])
            ->all();

        return ActionButton::make('Статус ИИ сообщения', '#')
            ->icon('heroicons.arrow-path')
            ->secondary()
            ->showInLine()
            ->canSee(function (): bool {
                if (! $this->can('update')) {
                    return false;
                }
                $item = $this->getItem();
                if (! $item instanceof ModerationAlert || $item->table_name !== ModerationAlertTableNameEnum::Author) {
                    return false;
                }

                return ApiChannelPost::query()
                    ->where('api_post_user_id', $item->table_row_id)
                    ->exists();
            })
            ->inOffCanvas(
                fn (): string => 'Изменить статус ИИ сообщения автора',
                function () use ($statusOptions): FormBuilder {
                    $item = $this->getItem();
                    $postOptions = [];
                    if ($item instanceof ModerationAlert && $item->table_name === ModerationAlertTableNameEnum::Author) {
                        $posts = ApiChannelPost::query()
                            ->where('api_post_user_id', $item->table_row_id)
                            ->orderByDesc('post_date')
                            ->limit(100)
                            ->get(['id', 'post_date', 'ai_parse_status', 'post']);
                        foreach ($posts as $post) {
                            $statusLabel = $post->ai_parse_status?->toString() ?? '—';
                            $snippet = Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $post->post)), 55);
                            $postOptions[(string) $post->id] = "#{$post->id} — {$statusLabel} — {$snippet}";
                        }
                    }

                    return FormBuilder::make()
                        ->name('moderation-author-post-ai-status-form')
                        ->fields([
                            Select::make('Сообщение', 'api_channel_post_id')
                                ->options($postOptions)
                                ->required()
                                ->searchable()
                                ->placeholder('Выберите сообщение'),
                            Select::make('Статус ИИ', 'ai_parse_status')
                                ->options($statusOptions)
                                ->required()
                                ->placeholder('Выберите статус'),
                        ])
                        ->asyncMethod('updateAuthorPostAiParseStatus', resource: $this)
                        ->submit('Применить');
                },
                isLeft: false,
            );
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
     * @param  ModerationAlert  $item
     * @return array<string, string[]|string>
     *
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
            HasOne::make('Пользователь', 'user', resource: new UserResource)->fields([
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
            HasOne::make('Пользователь', 'user', resource: new UserResource)->fields([
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
            Preview::make('Сообщения автора и статус ИИ', 'author_posts_ai_hint', static function ($item): string {
                if (! $item instanceof ModerationAlert || $item->table_name !== ModerationAlertTableNameEnum::Author) {
                    return '';
                }
                $posts = ApiChannelPost::query()
                    ->where('api_post_user_id', $item->table_row_id)
                    ->orderByDesc('post_date')
                    ->limit(40)
                    ->get(['id', 'ai_parse_status', 'post']);
                if ($posts->isEmpty()) {
                    return 'У автора нет сообщений.';
                }
                $rows = $posts->map(static function (ApiChannelPost $post): string {
                    $id = (int) $post->id;
                    $status = e($post->ai_parse_status?->toString() ?? '');
                    $snippet = e(Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $post->post)), 70));

                    return "<li><strong>#{$id}</strong> — {$status} — {$snippet}</li>";
                })->implode('');

                return '<p class="text-sm text-gray-600 mb-2">Быстрый обзор. Сменить статус: кнопка «Статус ИИ сообщения» сверху.</p><ul class="list-unstyled space-y-1">'.$rows.'</ul>';
            })->rawMode(),
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
        $fields[] = Preview::make('Сообщения автора и статус ИИ', 'author_posts_ai_hint_form', static function ($item): string {
            if (! $item instanceof ModerationAlert || $item->table_name !== ModerationAlertTableNameEnum::Author) {
                return '';
            }
            $posts = ApiChannelPost::query()
                ->where('api_post_user_id', $item->table_row_id)
                ->orderByDesc('post_date')
                ->limit(40)
                ->get(['id', 'ai_parse_status', 'post']);
            if ($posts->isEmpty()) {
                return 'У автора нет сообщений.';
            }
            $rows = $posts->map(static function (ApiChannelPost $post): string {
                $id = (int) $post->id;
                $status = e($post->ai_parse_status?->toString() ?? '');
                $snippet = e(Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $post->post)), 70));

                return "<li><strong>#{$id}</strong> — {$status} — {$snippet}</li>";
            })->implode('');

            return '<p class="text-sm text-gray-600 mb-2">Сменить статус ИИ: кнопка «Статус ИИ сообщения» в блоке действий.</p><ul class="list-unstyled space-y-1">'.$rows.'</ul>';
        })->rawMode();
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
