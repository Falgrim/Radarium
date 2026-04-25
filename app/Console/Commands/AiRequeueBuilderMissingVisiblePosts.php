<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Builder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Возвращает в очередь ИИ посты строителей у авторов публичного каталога «Строительство»,
 * у которых нет отображаемого Complete-сообщения (как в ApiPostUser::lastBuilderPost() + непустой текст).
 *
 * По умолчанию в очередь ставятся только посты со статусами Error, Empty, DontMatch (не Complete, не InQueue).
 */
class AiRequeueBuilderMissingVisiblePosts extends Command
{
    /** Статусы, которые по умолчанию можно вернуть в InQueue (без Complete и InQueue). */
    public const DEFAULT_REQUEUE_STATUSES = [
        ApiChannelPostStatusEnum::Error,
        ApiChannelPostStatusEnum::Empty,
        ApiChannelPostStatusEnum::DontMatch,
    ];

    protected $signature = 'app:ai_parse:requeue-builder-missing-visible-posts
                            {--dry-run : Показать выборку и статистику без изменений в БД}
                            {--apply : Явно разрешить запись (обновление ai_parse_status → InQueue)}
                            {--limit= : Максимум постов для одной партии (по id по возрастанию)}
                            {--status= : Список статусов через запятую: error,empty,dontmatch,duplicate,inqueue,complete или * (все кроме Complete и InQueue)}
                            {--backup-dir= : Каталог для JSON-снимка (по умолчанию storage/app/ai-reprocess)}';

    protected $description = 'Поставить в очередь ИИ builder-посты авторов каталога без видимого Complete-сообщения (dry-run / --apply + JSON-снимок)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Укажите ровно один режим: --dry-run (только просмотр) или --apply (изменение БД).');

            return self::INVALID;
        }

        $statuses = $this->resolveRequeueStatuses((string) ($this->option('status') ?? ''));
        if ($statuses === null) {
            return self::FAILURE;
        }

        if (in_array(ApiChannelPostStatusEnum::Complete, $statuses, true)) {
            $this->warn('В списке статусов указан Complete — обычно это не требуется для «пустого сообщения» в каталоге. Продолжаю по вашему выбору.');
        }

        $authorsQuery = $this->catalogAuthorsMissingVisibleBuilderPostQuery();
        $authorIds = $authorsQuery->pluck('id');
        $authorCount = $authorIds->count();

        $postsQuery = $this->targetPostsQuery($authorIds, $statuses);
        $postIds = $postsQuery->pluck('api_channel_posts.id')->unique()->values();
        $postCountTotal = $postIds->count();

        $this->line('Авторов в выборке (база каталога «Строительство» без видимого Complete-текста): '.$authorCount);
        $this->line('Постов, подходящих под условия (всего): '.$postCountTotal);

        $appliedLimit = null;
        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $appliedLimit = max(1, (int) $limit);
            $postIds = $postIds->sort()->take($appliedLimit)->values();
            $this->warn("Применён --limit={$appliedLimit}: в этой партии постов: ".$postIds->count());
        }

        $distribution = $this->statusDistributionForPosts($postIds);

        $this->newLine();
        $this->info('Распределение статусов постов в текущей выборке (после --limit, если указан):');
        foreach ($distribution as $row) {
            $status = $row->ai_parse_status;
            $statusValue = $status instanceof ApiChannelPostStatusEnum ? $status->value : (int) $status;
            $enum = $status instanceof ApiChannelPostStatusEnum
                ? $status
                : ApiChannelPostStatusEnum::tryFrom($statusValue);
            $label = $enum?->name ?? 'unknown';
            $this->line(sprintf('  %s (%s): %s', $label, $statusValue, $row->c));
        }

        $this->printSamples($authorIds, $postIds);

        if ($dryRun) {
            $this->newLine();
            $this->info('Режим --dry-run: изменений в БД нет. Для записи используйте --apply (и при необходимости --limit).');

            return self::SUCCESS;
        }

        if ($postIds->isEmpty()) {
            $this->info('Нет постов для обновления.');

            return self::SUCCESS;
        }

        $backupDir = $this->backupDirectory();
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $snapshotPath = $backupDir.DIRECTORY_SEPARATOR.'requeue-builder-missing-'.now()->format('Y-m-d_His').'.json';
        $snapshot = $this->buildSnapshot($postIds, $authorIds, $statuses, $appliedLimit);
        File::put($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('JSON-снимок сохранён: '.$snapshotPath);

        $updated = ApiChannelPost::query()
            ->whereIn('id', $postIds->all())
            ->update([
                'ai_parse_status' => ApiChannelPostStatusEnum::InQueue->value,
                'updated_at' => now(),
            ]);

        $this->info("Обновлено записей api_channel_posts: {$updated}. Запустите ИИ: php artisan app:ai_parse:builder");

        return self::SUCCESS;
    }

    /**
     * Авторы как в BuilderController::builders() (без фильтров региона/спец./ключевых слов),
     * у кого нет активного builder с постом Complete и непустым TRIM(post).
     */
    protected function catalogAuthorsMissingVisibleBuilderPostQuery(): EloquentBuilder
    {
        return ApiPostUser::query()
            ->whereHas('builders', function (EloquentBuilder $query) {
                $query->whereNotNull('api_channel_post_id')
                    ->where('api_channel_post_id', '>', 0)
                    ->where('status', ApiPostAiStatusEnum::Active);
            })
            ->whereDoesntHave('specialists', function (EloquentBuilder $query) {
                $query->where('status', ApiPostAiStatusEnum::Active);
            })
            ->where(function (EloquentBuilder $query) {
                $query->whereNotNull('phone')
                    ->orWhere('username', '<>', '');
            })
            ->whereDoesntHave('builders', function (EloquentBuilder $query) {
                $query->where('status', ApiPostAiStatusEnum::Active)
                    ->whereNotNull('api_channel_post_id')
                    ->where('api_channel_post_id', '>', 0)
                    ->whereHas('post', function (EloquentBuilder $postQuery) {
                        $postQuery->where('ai_parse_status', ApiChannelPostStatusEnum::Complete)
                            ->whereRaw('TRIM(COALESCE(`post`, \'\')) <> \'\'');
                    });
            });
    }

    /**
     * Посты активных builders этих авторов: канал Builder, непустой текст, статус из списка, не InQueue.
     *
     * @param  Collection<int, int>  $authorIds
     * @param  array<int, ApiChannelPostStatusEnum>  $statuses
     */
    protected function targetPostsQuery(Collection $authorIds, array $statuses): EloquentBuilder
    {
        if ($authorIds->isEmpty()) {
            return ApiChannelPost::query()->whereRaw('0 = 1');
        }

        $statusValues = array_map(static fn (ApiChannelPostStatusEnum $s) => $s->value, $statuses);

        return ApiChannelPost::query()
            ->select('api_channel_posts.id', 'api_channel_posts.ai_parse_status')
            ->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->join(Builder::table(), 'builders.api_channel_post_id', '=', 'api_channel_posts.id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Builder)
            ->whereIn('builders.api_post_user_id', $authorIds)
            ->where('builders.status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_posts.post')
            ->whereRaw('TRIM(api_channel_posts.post) <> \'\'')
            ->whereNot('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->whereIn('api_channel_posts.ai_parse_status', $statusValues)
            ->orderBy('api_channel_posts.id');
    }

    /**
     * @param  Collection<int, int>  $postIds
     * @return array<int, object{ai_parse_status:int|ApiChannelPostStatusEnum, c:int}>
     */
    protected function statusDistributionForPosts(Collection $postIds): array
    {
        if ($postIds->isEmpty()) {
            return [];
        }

        return ApiChannelPost::query()
            ->selectRaw('ai_parse_status, COUNT(*) as c')
            ->whereIn('id', $postIds->all())
            ->groupBy('ai_parse_status')
            ->orderBy('ai_parse_status')
            ->get()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $authorIds
     * @param  Collection<int, int>  $postIds
     */
    protected function printSamples(Collection $authorIds, Collection $postIds): void
    {
        $this->newLine();
        $this->info('Примеры author_id (до 5):');
        foreach ($authorIds->take(5) as $id) {
            $this->line('  '.$id);
        }
        $this->info('Примеры post_id (до 5):');
        foreach ($postIds->take(5) as $id) {
            $this->line('  '.$id);
        }
    }

    /**
     * @return array<int, ApiChannelPostStatusEnum>|null
     */
    protected function resolveRequeueStatuses(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || strtolower($raw) === 'default') {
            return self::DEFAULT_REQUEUE_STATUSES;
        }

        if ($raw === '*' || strtolower($raw) === 'all') {
            $out = [];
            foreach (ApiChannelPostStatusEnum::cases() as $case) {
                if ($case === ApiChannelPostStatusEnum::Complete || $case === ApiChannelPostStatusEnum::InQueue) {
                    continue;
                }
                $out[] = $case;
            }

            return $out;
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $map = [
            'complete' => ApiChannelPostStatusEnum::Complete,
            'inqueue' => ApiChannelPostStatusEnum::InQueue,
            'error' => ApiChannelPostStatusEnum::Error,
            'empty' => ApiChannelPostStatusEnum::Empty,
            'dontmatch' => ApiChannelPostStatusEnum::DontMatch,
            'duplicate' => ApiChannelPostStatusEnum::Duplicate,
        ];

        $result = [];
        foreach ($parts as $part) {
            $key = strtolower($part);
            if (ctype_digit($part)) {
                $val = (int) $part;
                $enum = ApiChannelPostStatusEnum::tryFrom($val);
                if ($enum === null) {
                    $this->error("Неизвестное числовое значение статуса: {$part}");

                    return null;
                }
                $result[] = $enum;

                continue;
            }
            if (! isset($map[$key])) {
                $this->error("Неизвестный статус в --status=: {$part}. Допустимо: ".implode(',', array_keys($map)).', * или числовые значения enum.');

                return null;
            }
            $result[] = $map[$key];
        }

        if ($result === []) {
            $this->error('В --status= не указано ни одного статуса. Оставьте опцию пустой для набора по умолчанию (error,empty,dontmatch).');

            return null;
        }

        return array_values(array_unique($result, SORT_REGULAR));
    }

    protected function backupDirectory(): string
    {
        $dir = (string) ($this->option('backup-dir') ?? '');
        $dir = trim($dir);

        return $dir !== '' ? $dir : storage_path('app/ai-reprocess');
    }

    /**
     * @param  Collection<int, int>  $postIds
     * @param  Collection<int, int>  $authorIds
     * @param  array<int, ApiChannelPostStatusEnum>  $statuses
     */
    protected function buildSnapshot(Collection $postIds, Collection $authorIds, array $statuses, ?int $limit): array
    {
        $posts = ApiChannelPost::query()
            ->whereIn('id', $postIds->all())
            ->get()
            ->mapWithKeys(function (ApiChannelPost $post) {
                return [
                    $post->id => [
                        'id' => $post->id,
                        'api_post_user_id' => $post->api_post_user_id,
                        'api_channel_id' => $post->api_channel_id,
                        'post_id' => $post->post_id,
                        'post_date' => $post->post_date?->format('Y-m-d H:i:s'),
                        'ai_parse_status' => $post->ai_parse_status instanceof ApiChannelPostStatusEnum
                            ? $post->ai_parse_status->value
                            : $post->ai_parse_status,
                        'ai_date' => $post->ai_date?->format('Y-m-d H:i:s'),
                        'ai_provider_used' => $post->ai_provider_used,
                        'post' => $post->post,
                    ],
                ];
            });

        $builders = Builder::query()
            ->whereIn('api_channel_post_id', $postIds->all())
            ->get()
            ->groupBy('api_channel_post_id')
            ->map(fn (Collection $group) => $group->map(fn (Builder $b) => $b->getAttributes())->values()->all());

        return [
            'run_at' => now()->toIso8601String(),
            'command' => 'app:ai_parse:requeue-builder-missing-visible-posts',
            'options' => [
                'apply' => true,
                'limit' => $limit,
                'status' => array_map(static fn (ApiChannelPostStatusEnum $e) => $e->name, $statuses),
                'backup_dir' => $this->backupDirectory(),
            ],
            'author_ids' => $authorIds->values()->all(),
            'post_ids' => $postIds->values()->all(),
            'posts' => $posts->values()->all(),
            'builders_by_post_id' => $builders->toArray(),
        ];
    }
}
