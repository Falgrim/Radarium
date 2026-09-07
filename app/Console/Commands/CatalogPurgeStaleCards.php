<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Builder as BuilderCard;
use App\Models\Specialist;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Мягко удаляет карточки каталога, созданные из сообщений старше указанной даты.
 *
 * Когда очередь ИИ разбирает накопившийся архив, из сообщений двух-трёхлетней давности появляются
 * карточки, которые в каталоге не нужны: автор давно недоступен, а предложение неактуально.
 * Команда убирает такие карточки из выдачи и из админки; строки остаются в БД под `deleted_at`,
 * поэтому по JSON-снимку их можно вернуть.
 *
 * Посты-источники намеренно остаются в статусе Complete: в этом статусе ИИ их повторно не берёт,
 * и карточка не появится снова сама по себе.
 */
class CatalogPurgeStaleCards extends Command
{
    /** @var array<string, class-string<BuilderCard|Specialist>> */
    private const CARD_MODELS = [
        'builder' => BuilderCard::class,
        'specialist' => Specialist::class,
    ];

    protected $signature = 'app:catalog:purge-stale-cards
                            {--type=builder : Каталог: builder или specialist}
                            {--before= : Дата в формате Y-m-d; под чистку попадают карточки с post_date раньше неё}
                            {--chunk=500 : Размер партии удаления}
                            {--backup-dir= : Каталог JSON-снимка, по умолчанию storage/app/catalog-purge}
                            {--dry-run : Показать выборку без изменений в БД}
                            {--apply : Применить мягкое удаление}';

    protected $description = 'Убрать из каталога карточки по сообщениям старше указанной даты (мягкое удаление со снимком для откатa)';

    public function handle(): int
    {
        $type = mb_strtolower(trim((string) $this->option('type')));
        if (! array_key_exists($type, self::CARD_MODELS)) {
            $this->error('Параметр --type должен быть одним из: '.implode(', ', array_keys(self::CARD_MODELS)).'.');

            return self::INVALID;
        }

        $before = $this->parseBefore((string) $this->option('before'));
        if ($before === null) {
            return self::INVALID;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $isApply = (bool) $this->option('apply');
        if ($isDryRun === $isApply) {
            $this->error('Укажите ровно один режим: --dry-run или --apply.');

            return self::INVALID;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $modelClass = self::CARD_MODELS[$type];

        $this->line('Каталог: '.$type);
        $this->line('Под чистку попадают карточки по сообщениям до '.$before->format('Y-m-d'));

        $ids = $this->targetQuery($modelClass, $before)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Карточек по сообщениям старше указанной даты нет.');

            return self::SUCCESS;
        }

        $this->info('Карточек в выборке: '.$ids->count());
        $this->printYearDistribution($modelClass, $before);
        $this->printStatusDistribution($modelClass, $before);
        $this->printSkippedWithoutDate($modelClass);

        if ($isDryRun) {
            $this->info('Режим --dry-run: изменений в БД нет.');

            return self::SUCCESS;
        }

        $snapshot = $this->writeSnapshot($type, $before, $ids->all());
        $this->line('Снимок для откатa сохранён: '.$snapshot);

        $deleted = 0;
        foreach ($ids->chunk($chunk) as $batch) {
            $deleted += $modelClass::query()->whereIn('id', $batch->all())->delete();
        }

        $this->info('Убрано из каталога: '.$deleted);
        $this->comment('Вернуть при необходимости (php artisan tinker):');
        $this->comment(sprintf(
            '  %s::withTrashed()->whereIn(\'id\', json_decode(file_get_contents(\'%s\'), true)[\'card_ids\'])->restore();',
            '\\'.$modelClass,
            $snapshot
        ));

        return self::SUCCESS;
    }

    /**
     * Дата принимается только полным днём: диапазон чистки должен читаться из команды однозначно.
     */
    private function parseBefore(string $raw): ?CarbonImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            $this->error('Укажите дату отсечения: --before=Y-m-d (например --before=2026-03-01).');

            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw);
        } catch (Throwable) {
            $date = null;
        }

        // Carbon переполняет невозможные даты («2026-13-45» → следующий год), поэтому сверяем обратное представление.
        if ($date === null || $date->format('Y-m-d') !== $raw) {
            $this->error('Параметр --before должен быть датой в формате Y-m-d, получено: '.$raw);

            return null;
        }

        return $date;
    }

    /**
     * @param  class-string<BuilderCard|Specialist>  $modelClass
     * @return Builder<BuilderCard|Specialist>
     */
    private function targetQuery(string $modelClass, CarbonImmutable $before): Builder
    {
        return $modelClass::query()
            ->whereNotNull('post_date')
            ->where('post_date', '<', $before);
    }

    /**
     * @param  class-string<BuilderCard|Specialist>  $modelClass
     */
    private function printYearDistribution(string $modelClass, CarbonImmutable $before): void
    {
        $rows = $this->targetQuery($modelClass, $before)
            ->selectRaw('YEAR(post_date) as post_year, COUNT(*) as total')
            ->groupBy('post_year')
            ->orderBy('post_year')
            ->get();

        $this->line('По годам сообщения:');
        foreach ($rows as $row) {
            $this->line(sprintf('  %s: %s', $row->post_year, $row->total));
        }
    }

    /**
     * @param  class-string<BuilderCard|Specialist>  $modelClass
     */
    private function printStatusDistribution(string $modelClass, CarbonImmutable $before): void
    {
        $rows = $this->targetQuery($modelClass, $before)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $this->line('По статусу карточки:');
        foreach ($rows as $row) {
            $status = $row->status;
            $this->line(sprintf('  %s: %s', $status?->toString() ?? 'без статуса', $row->total));
        }
    }

    /**
     * Карточки без даты сообщения под условие не попадают — оператор должен видеть, что они остались.
     *
     * @param  class-string<BuilderCard|Specialist>  $modelClass
     */
    private function printSkippedWithoutDate(string $modelClass): void
    {
        $withoutDate = $modelClass::query()->whereNull('post_date')->count();
        if ($withoutDate > 0) {
            $this->warn('Карточек без post_date (не затрагиваются): '.$withoutDate);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function writeSnapshot(string $type, CarbonImmutable $before, array $ids): string
    {
        $dir = trim((string) $this->option('backup-dir')) ?: storage_path('app/catalog-purge');
        File::ensureDirectoryExists($dir);

        $path = rtrim($dir, '/\\').'/purge-stale-'.$type.'-'.CarbonImmutable::now()->format('Ymd-His').'.json';

        File::put($path, json_encode([
            'type' => $type,
            'before' => $before->format('Y-m-d'),
            'card_ids' => $ids,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $path;
    }
}
