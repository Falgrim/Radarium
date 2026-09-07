<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

/**
 * Убирает из очереди ИИ сообщения глубже заданной актуальной глубины.
 *
 * Очередь разбирается партиями по 100 сообщений, поэтому накопленный архив занимает все места
 * каждого запуска и свежие сообщения не доходят до каталога. Команда переводит устаревшие
 * сообщения в DontMatch — статус вне выборки `--status=unprocessed`, поэтому обычный requeue
 * не вернёт их в очередь случайно.
 */
class AiArchiveStaleQueue extends Command
{
    private const DATA_TYPES = [
        'all' => null,
        'specialist' => ApiDataTypeEnum::Specialist,
        'builder' => ApiDataTypeEnum::Builder,
        'company' => ApiDataTypeEnum::Company,
    ];

    protected $signature = 'app:ai_parse:archive-stale-queue
                            {--type=builder : Тип источников: builder, specialist, company, all}
                            {--months=6 : Актуальная глубина в месяцах; сообщения старше уходят в архив}
                            {--chunk=500 : Размер партии обновления}
                            {--backup-dir= : Каталог JSON-снимка, по умолчанию storage/app/ai-archive-queue}
                            {--dry-run : Показать выборку без изменений в БД}
                            {--apply : Применить изменения}';

    protected $description = 'Убрать из очереди ИИ устаревшие сообщения, чтобы очередь дошла до свежих';

    public function handle(): int
    {
        $typeOption = mb_strtolower(trim((string) $this->option('type')));
        if (! array_key_exists($typeOption, self::DATA_TYPES)) {
            $this->error('Параметр --type должен быть одним из: '.implode(', ', array_keys(self::DATA_TYPES)).'.');

            return self::INVALID;
        }
        $dataType = self::DATA_TYPES[$typeOption];

        $months = (int) $this->option('months');
        if ($months < 1) {
            $this->error('Параметр --months должен быть целым числом больше нуля.');

            return self::INVALID;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $isApply = (bool) $this->option('apply');
        if ($isDryRun === $isApply) {
            $this->error('Укажите ровно один режим: --dry-run или --apply.');

            return self::INVALID;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $cutoff = CarbonImmutable::now()->subMonths($months)->startOfDay();

        $this->line('Тип источников: '.$typeOption);
        $this->line('Актуальная глубина: '.$months.' мес. — в архив уходят сообщения до '.$cutoff->format('Y-m-d'));

        $ids = $this->targetQuery($dataType, $cutoff)->pluck('api_channel_posts.id');

        if ($ids->isEmpty()) {
            $this->info('Устаревших сообщений в очереди нет.');

            return self::SUCCESS;
        }

        $this->info('Сообщений в очереди старше '.$cutoff->format('Y-m-d').': '.$ids->count());
        $this->printYearDistribution($dataType, $cutoff);

        if ($isDryRun) {
            $this->info('Режим --dry-run: изменений в БД нет.');

            return self::SUCCESS;
        }

        $snapshot = $this->writeSnapshot($typeOption, $cutoff, $ids->all());
        $this->line('Снимок для отката сохранён: '.$snapshot);

        $marker = json_encode([
            'archived_stale_queue' => true,
            'archived_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
            'cutoff' => $cutoff->format('Y-m-d H:i:s'),
            'months' => $months,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $archived = 0;
        foreach ($ids->chunk($chunk) as $batch) {
            $archived += ApiChannelPost::query()
                ->whereIn('id', $batch->all())
                ->update([
                    'ai_parse_status' => ApiChannelPostStatusEnum::DontMatch->value,
                    'ai_date' => CarbonImmutable::now(),
                    'ai_result' => $marker,
                ]);
        }

        $this->info('Убрано из очереди: '.$archived);
        $this->comment('Вернуть при необходимости: php artisan app:ai_parse:requeue --type='.$typeOption.' --status=dontmatch --from=<Y-m-d> --to=<Y-m-d> --date-field=post_date');

        return self::SUCCESS;
    }

    /**
     * @return Builder<ApiChannelPost>
     */
    private function targetQuery(?ApiDataTypeEnum $dataType, CarbonImmutable $cutoff): Builder
    {
        return ApiChannelPost::query()
            ->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->when(
                $dataType !== null,
                static function (Builder $query) use ($dataType): void {
                    $query->where('api_channels.is_company', $dataType);
                }
            )
            ->where('api_channel_posts.post_date', '<', $cutoff);
    }

    private function printYearDistribution(?ApiDataTypeEnum $dataType, CarbonImmutable $cutoff): void
    {
        $rows = $this->targetQuery($dataType, $cutoff)
            ->selectRaw('YEAR(api_channel_posts.post_date) as post_year, COUNT(*) as total')
            ->groupBy('post_year')
            ->orderBy('post_year')
            ->get();

        foreach ($rows as $row) {
            $this->line(sprintf('  %s: %s', $row->post_year ?? 'без даты', $row->total));
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function writeSnapshot(string $type, CarbonImmutable $cutoff, array $ids): string
    {
        $dir = trim((string) $this->option('backup-dir')) ?: storage_path('app/ai-archive-queue');
        File::ensureDirectoryExists($dir);

        $path = rtrim($dir, '/\\').'/archive-stale-'.$type.'-'.CarbonImmutable::now()->format('Ymd-His').'.json';

        File::put($path, json_encode([
            'type' => $type,
            'cutoff' => $cutoff->format('Y-m-d H:i:s'),
            'api_channel_post_ids' => $ids,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $path;
    }
}
