<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannelPost;
use App\Services\AiReprocessService;
use Illuminate\Console\Command;

/**
 * Единая команда повторного прогона сообщений через ИИ (заменяет прежние
 * app:ai_parse:reset-builder-queue и app:ai_parse:reset-specialist-queue).
 */
class AiRequeuePosts extends Command
{
    private const DATA_TYPES = [
        'all' => null,
        'specialist' => ApiDataTypeEnum::Specialist,
        'builder' => ApiDataTypeEnum::Builder,
        'company' => ApiDataTypeEnum::Company,
    ];

    protected $signature = 'app:ai_parse:requeue
                            {--type=all : Тип источников: all, specialist, builder, company}
                            {--status=unprocessed : unprocessed (Error+Empty), all (ещё DontMatch+Complete) или список: error,empty,dontmatch,complete,duplicate}
                            {--from= : Начало периода (Y-m-d); вместе с --to}
                            {--to= : Конец периода включительно (Y-m-d); вместе с --from}
                            {--date-field=ai_date : Поле периода: ai_date, post_date или created_at}
                            {--provider= : Фильтр по ai_provider_used (пусто — любой)}
                            {--keep-metadata : Не обнулять ai_result, ai_date, ai_provider_used}
                            {--no-dispatch : Только вернуть в очередь, без запуска фоновой обработки}
                            {--dry-run : Показать выборку без изменений в БД}';

    protected $description = 'Вернуть сообщения в очередь ИИ и запустить повторную обработку';

    public function handle(AiReprocessService $service): int
    {
        $typeOption = mb_strtolower(trim((string) $this->option('type')));
        if (! array_key_exists($typeOption, self::DATA_TYPES)) {
            $this->error('Параметр --type должен быть одним из: '.implode(', ', array_keys(self::DATA_TYPES)).'.');

            return self::INVALID;
        }
        $dataType = self::DATA_TYPES[$typeOption];

        $statuses = $service->parseStatuses((string) $this->option('status'));
        if ($statuses === null) {
            $this->error('Параметр --status содержит неизвестный статус. Допустимо: unprocessed, all или список error,empty,dontmatch,complete,duplicate,inqueue.');

            return self::INVALID;
        }

        $dateField = (string) $this->option('date-field');
        if (! in_array($dateField, ['ai_date', 'post_date', 'created_at'], true)) {
            $this->error('Параметр --date-field должен быть ai_date, post_date или created_at.');

            return self::INVALID;
        }

        [$from, $to] = [$this->option('from'), $this->option('to')];
        if (($from === null) !== ($to === null)) {
            $this->error('Параметры --from и --to указываются вместе.');

            return self::INVALID;
        }

        $from = $from !== null ? $from.' 00:00:00' : null;
        $to = $to !== null ? $to.' 23:59:59' : null;
        $provider = (string) $this->option('provider');

        $this->line('Тип источников: '.$typeOption);
        $this->line('Статусы для возврата: '.implode(', ', array_map(
            static fn (ApiChannelPostStatusEnum $status): string => $status->name,
            $statuses
        )));
        $this->line('Период: '.($from !== null ? $from.' — '.$to.' по полю '.$dateField : 'без ограничения'));
        $this->line('Провайдер: '.($provider !== '' ? $provider : 'любой'));

        if ($this->option('dry-run')) {
            $ids = $service->targetIds($statuses, $dataType, $from, $to, $dateField, $provider);
            $this->info('Записей для возврата в очередь: '.$ids->count());
            $this->printStatusDistribution($ids->all());
            $this->info('Режим --dry-run: изменений в БД нет.');

            return self::SUCCESS;
        }

        $requeued = $service->requeue(
            statuses: $statuses,
            dataType: $dataType,
            from: $from,
            to: $to,
            dateField: $dateField,
            provider: $provider,
            clearMetadata: ! $this->option('keep-metadata'),
        );

        $this->info("Возвращено в очередь: {$requeued}");

        if ($this->option('no-dispatch')) {
            $this->comment('Фоновая обработка не запускалась (--no-dispatch). Запустите вручную: php artisan app:ai_parse:builder | :specialist | :company');

            return self::SUCCESS;
        }

        $pending = $service->dispatchProcessing($dataType);

        $this->info($pending > 0
            ? "Фоновая обработка запущена, сообщений в очереди: {$pending} (нужен запущенный queue:work)"
            : 'Очередь пуста, обработка не требуется');

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     */
    private function printStatusDistribution(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $rows = ApiChannelPost::query()
            ->selectRaw('ai_parse_status, COUNT(*) as total')
            ->whereIn('id', $ids)
            ->groupBy('ai_parse_status')
            ->get();

        foreach ($rows as $row) {
            $status = $row->ai_parse_status;
            $label = $status instanceof ApiChannelPostStatusEnum
                ? $status->name
                : (string) $status;

            $this->line(sprintf('  %s: %s', $label, $row->total));
        }
    }
}
