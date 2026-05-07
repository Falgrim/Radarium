<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Models\ApiChannelPost;
use App\Models\ModerationAlert;
use App\Services\Admin\ModerationAuthorPostCatalogService;
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
    protected bool $saveFilterState = true;

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
            $this->authorReprocessAiButton(),
            $this->authorRemoveFromCatalogButton(),
        ];
    }

    /**
     * @return list<ActionButton>
     */
    public function formButtons(): array
    {
        return [
            $this->authorReprocessAiButton(),
            $this->authorRemoveFromCatalogButton(),
        ];
    }

    /**
     * Карточки каталога по посту → «Отключено», пост → «В очереди» (повторный прогон ИИ).
     */
    public function requeueAuthorPostForAi(MoonShineRequest $request): MoonShineJsonResponse
    {
        if (! $this->can('update')) {
            return MoonShineJsonResponse::make()
                ->toast('Недостаточно прав для изменения', ToastType::ERROR);
        }

        $alert = $this->getItem();
        if (! $alert instanceof ModerationAlert || ! $this->moderationCatalogActionsCanSeeForAlert($alert)) {
            return MoonShineJsonResponse::make()
                ->toast('Для этого обращения действие недоступно', ToastType::ERROR);
        }

        $validated = $request->validate([
            'api_channel_post_id' => ['nullable', 'integer', 'exists:api_channel_posts,id'],
        ]);

        $postId = $validated['api_channel_post_id'] ?? null;
        if ($postId === null) {
            $postId = $alert->resolveApiChannelPostId();
        } else {
            $postId = (int) $postId;
        }

        if ($postId === null) {
            return MoonShineJsonResponse::make()
                ->toast('Не удалось определить сообщение: выберите пост в списке или укажите связь с карточкой каталога.', ToastType::ERROR);
        }

        $post = ApiChannelPost::query()->findOrFail($postId);
        if (! $alert->allowsApiChannelPost($post)) {
            return MoonShineJsonResponse::make()
                ->toast('Сообщение не соответствует этому обращению модерации', ToastType::ERROR);
        }

        app(ModerationAuthorPostCatalogService::class)->requeueForAi($post);

        $alert->status = ModerationAlertStatusEnum::AiReprocessing;
        $alert->save();

        return MoonShineJsonResponse::make()
            ->toast('Карточки сняты с публикации, сообщение поставлено в очередь ИИ', ToastType::SUCCESS)
            ->redirect($this->formPageUrl($alert));
    }

    /**
     * Только снять карточки каталога по посту; статус парсинга сообщения не меняется.
     */
    public function removeAuthorPostFromCatalog(MoonShineRequest $request): MoonShineJsonResponse
    {
        if (! $this->can('update')) {
            return MoonShineJsonResponse::make()
                ->toast('Недостаточно прав для изменения', ToastType::ERROR);
        }

        $alert = $this->getItem();
        if (! $alert instanceof ModerationAlert || ! $this->moderationCatalogActionsCanSeeForAlert($alert)) {
            return MoonShineJsonResponse::make()
                ->toast('Для этого обращения действие недоступно', ToastType::ERROR);
        }

        $validated = $request->validate([
            'api_channel_post_id' => ['nullable', 'integer', 'exists:api_channel_posts,id'],
        ]);

        $postId = $validated['api_channel_post_id'] ?? null;
        if ($postId === null) {
            $postId = $alert->resolveApiChannelPostId();
        } else {
            $postId = (int) $postId;
        }

        if ($postId === null) {
            return MoonShineJsonResponse::make()
                ->toast('Не удалось определить сообщение: выберите пост в списке или укажите связь с карточкой каталога.', ToastType::ERROR);
        }

        $post = ApiChannelPost::query()->findOrFail($postId);
        if (! $alert->allowsApiChannelPost($post)) {
            return MoonShineJsonResponse::make()
                ->toast('Сообщение не соответствует этому обращению модерации', ToastType::ERROR);
        }

        app(ModerationAuthorPostCatalogService::class)->removeFromCatalog($post);

        $alert->status = ModerationAlertStatusEnum::RemovedFromCatalog;
        $alert->save();

        return MoonShineJsonResponse::make()
            ->toast('Карточки по сообщению сняты с публикации (без очереди ИИ)', ToastType::SUCCESS)
            ->redirect($this->formPageUrl($alert));
    }

    private function moderationCatalogActionsCanSeeForAlert(ModerationAlert $alert): bool
    {
        if ($alert->resolveApiChannelPostId() !== null) {
            return true;
        }

        return $alert->table_name === ModerationAlertTableNameEnum::Author
            && ApiChannelPost::query()
                ->where('api_post_user_id', $alert->table_row_id)
                ->exists();
    }

    private function moderationCatalogActionsCanSee(): bool
    {
        if (! $this->can('update')) {
            return false;
        }
        $item = $this->getItem();

        return $item instanceof ModerationAlert && $this->moderationCatalogActionsCanSeeForAlert($item);
    }

    /**
     * @return list<Field>
     */
    private function requeueAiFormFields(): array
    {
        $item = $this->getItem();
        if (! $item instanceof ModerationAlert) {
            return [];
        }

        $hint = '<p class="text-sm text-gray-600 dark:text-gray-400">Для выбранного сообщения: все связанные карточки каталога получают статус «Отключено», у сообщения — «В очереди» (очередь парсинга ИИ). Пока ИИ не обработает снова, запись не в публичном каталоге и не в отчёте активных авторов.</p>';

        $resolved = $item->resolveApiChannelPostId();
        if ($resolved !== null) {
            $post = ApiChannelPost::query()->find($resolved);
            $line = $post instanceof ApiChannelPost
                ? sprintf(
                    '#%d — %s — %s',
                    $post->id,
                    $post->ai_parse_status?->toString() ?? '—',
                    Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $post->post)), 120)
                )
                : 'Пост #'.$resolved;

            return [
                Preview::make('Сообщение', 'reprocess_target', fn (): string => '<div class="text-sm"><p class="font-medium mb-1">Будет обработан пост:</p><p>'.e($line).'</p><p class="mt-2 text-gray-600">Отдельно выбирать сообщение не нужно — оно привязано к этому обращению (карточка каталога или поле в тикете).</p></div>')
                    ->rawMode(),
                Preview::make('', 'reprocess_hint', static fn (): string => $hint)
                    ->rawMode(),
            ];
        }

        return [
            Select::make('Сообщение', 'api_channel_post_id')
                ->options($this->authorPostSelectOptions())
                ->required()
                ->searchable()
                ->placeholder('Выберите сообщение (обращение по автору без привязки к посту)'),
            Preview::make('', 'reprocess_hint', static fn (): string => $hint)
                ->rawMode(),
        ];
    }

    /**
     * @return list<Field>
     */
    private function removeFromCatalogFormFields(): array
    {
        $item = $this->getItem();
        if (! $item instanceof ModerationAlert) {
            return [];
        }

        $hint = '<p class="text-sm text-gray-600 dark:text-gray-400">Для выбранного сообщения: связанные карточки каталога переводятся в «Отключено». Статус парсинга сообщения не меняется — повторная обработка ИИ не запускается. Автор пропадёт из каталога и отчёта по этому типу, если у него не останется других активных опубликованных карточек.</p>';

        $resolved = $item->resolveApiChannelPostId();
        if ($resolved !== null) {
            $post = ApiChannelPost::query()->find($resolved);
            $line = $post instanceof ApiChannelPost
                ? sprintf(
                    '#%d — %s — %s',
                    $post->id,
                    $post->ai_parse_status?->toString() ?? '—',
                    Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $post->post)), 120)
                )
                : 'Пост #'.$resolved;

            return [
                Preview::make('Сообщение', 'remove_target', fn (): string => '<div class="text-sm"><p class="font-medium mb-1">Будет снято с публикации:</p><p>'.e($line).'</p><p class="mt-2 text-gray-600">Отдельно выбирать сообщение не нужно.</p></div>')
                    ->rawMode(),
                Preview::make('', 'remove_hint', static fn (): string => $hint)
                    ->rawMode(),
            ];
        }

        return [
            Select::make('Сообщение', 'api_channel_post_id')
                ->options($this->authorPostSelectOptions())
                ->required()
                ->searchable()
                ->placeholder('Выберите сообщение (обращение по автору без привязки к посту)'),
            Preview::make('', 'remove_hint', static fn (): string => $hint)
                ->rawMode(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authorPostSelectOptions(): array
    {
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

        return $postOptions;
    }

    protected function authorReprocessAiButton(): ActionButton
    {
        return ActionButton::make('Повторная ИИ обработка', '#')
            ->icon('heroicons.arrow-path')
            ->primary()
            ->showInLine()
            ->canSee(fn (): bool => $this->moderationCatalogActionsCanSee())
            ->inOffCanvas(
                fn (): string => 'Повторная ИИ обработка',
                function (): FormBuilder {
                    return FormBuilder::make()
                        ->name('moderation-author-reprocess-ai-form')
                        ->fields($this->requeueAiFormFields())
                        ->asyncMethod('requeueAuthorPostForAi', resource: $this)
                        ->submit('Запустить');
                },
                isLeft: false,
            );
    }

    protected function authorRemoveFromCatalogButton(): ActionButton
    {
        return ActionButton::make('Убрать из каталога', '#')
            ->icon('heroicons.eye-slash')
            ->secondary()
            ->showInLine()
            ->canSee(fn (): bool => $this->moderationCatalogActionsCanSee())
            ->inOffCanvas(
                fn (): string => 'Убрать из каталога',
                function (): FormBuilder {
                    return FormBuilder::make()
                        ->name('moderation-author-remove-catalog-form')
                        ->fields($this->removeFromCatalogFormFields())
                        ->asyncMethod('removeAuthorPostFromCatalog', resource: $this)
                        ->submit('Убрать');
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

                return '<p class="text-sm text-gray-600 mb-2">Быстрый обзор. Действия: «Повторная ИИ обработка» или «Убрать из каталога» (кнопки сверху).</p><ul class="list-unstyled space-y-1">'.$rows.'</ul>';
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

            return '<p class="text-sm text-gray-600 mb-2">«Повторная ИИ обработка» — снять с каталога и поставить в очередь ИИ. «Убрать из каталога» — только отключить карточки без очереди.</p><ul class="list-unstyled space-y-1">'.$rows.'</ul>';
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
