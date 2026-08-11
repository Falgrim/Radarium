<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Инвентаризация и включение отключённых каналов проектировщиков (api_channels.is_company = Specialist).
 */
class EnableSpecialistChannelsCommand extends Command
{
    protected $signature = 'app:channels:enable-specialists
                            {--dry-run : Показать список без изменений в БД}
                            {--apply : Включить выбран (status = Active)}
                            {--id=* : Ограничить конкретными id каналов (можно несколько)}
                            {--source= : Фильтр channel_source: telegram|vk (пустое — все)}
                            {--include-active : Показать также уже активные каналы (только отчёт; --apply по-прежнему трогает только Disabled)}';

    protected $description = 'Инвентаризация и включение отключённых каналов проектировщиков (dry-run / --apply)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Укажите ровно один режим: --dry-run (только просмотр) или --apply (изменение БД).');

            return self::INVALID;
        }

        $source = $this->resolveSourceFilter((string) ($this->option('source') ?? ''));
        if ($source === false) {
            return self::FAILURE;
        }

        $ids = $this->normalizeIds($this->option('id'));
        $includeActive = (bool) $this->option('include-active');

        $reportQuery = $this->baseSpecialistChannelsQuery($source, $ids);
        if (! $includeActive) {
            $reportQuery->where('status', ApiChannelStatusEnum::Disabled);
        }

        /** @var Collection<int, ApiChannel> $channels */
        $channels = $reportQuery
            ->with('apiAi')
            ->orderBy('status')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('Каналов проектировщиков по заданным условиям не найдено.');

            return self::SUCCESS;
        }

        $this->printReport($channels);

        $toEnable = $channels->filter(
            static fn (ApiChannel $channel): bool => $channel->status === ApiChannelStatusEnum::Disabled
        );

        $this->newLine();
        $this->line('К включению (status = Disabled): '.$toEnable->count());

        if ($dryRun) {
            $this->info('Режим --dry-run: изменений в БД нет. Для включения используйте --apply.');

            return self::SUCCESS;
        }

        if ($toEnable->isEmpty()) {
            $this->info('Нет отключённых каналов для включения.');

            return self::SUCCESS;
        }

        $updated = ApiChannel::query()
            ->whereIn('id', $toEnable->pluck('id')->all())
            ->update([
                'status' => ApiChannelStatusEnum::Active->value,
                'updated_at' => now(),
            ]);

        $this->info("Включено каналов: {$updated}. Статус → Active.");

        return self::SUCCESS;
    }

    /**
     * @param  ApiChannelSourceEnum|null|false  $source  false = ошибка валидации уже показана
     */
    protected function baseSpecialistChannelsQuery(ApiChannelSourceEnum|null|false $source, array $ids): Builder
    {
        $query = ApiChannel::query()
            ->where('is_company', ApiDataTypeEnum::Specialist);

        if ($source instanceof ApiChannelSourceEnum) {
            $query->where('channel_source', $source);
        }

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query;
    }

    /**
     * @return ApiChannelSourceEnum|null|false null = без фильтра; false = ошибка
     */
    protected function resolveSourceFilter(string $raw): ApiChannelSourceEnum|null|false
    {
        $raw = trim(strtolower($raw));
        if ($raw === '') {
            return null;
        }

        $enum = ApiChannelSourceEnum::tryFrom($raw);
        if ($enum === null) {
            $this->error('Параметр --source должен быть telegram или vk.');

            return false;
        }

        return $enum;
    }

    /**
     * @param  array<int, mixed>|null  $rawIds
     * @return list<int>
     */
    protected function normalizeIds(mixed $rawIds): array
    {
        if (! is_array($rawIds) || $rawIds === []) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $ids[] = (int) $raw;
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  Collection<int, ApiChannel>  $channels
     */
    protected function printReport(Collection $channels): void
    {
        $rows = [];
        $warnings = 0;

        foreach ($channels as $channel) {
            $apiAi = $channel->apiAi;
            $apiSource = $apiAi?->api_source;
            $apiSourceLabel = $apiSource instanceof ApiAiSourceEnum
                ? $apiSource->toString().' ('.$apiSource->value.')'
                : ($apiSource ?? '—');
            $aiStatus = $apiAi?->status;
            $aiStatusLabel = $aiStatus instanceof ApiAiStatusEnum
                ? $aiStatus->toString()
                : '—';

            $flags = [];
            if ($apiAi === null) {
                $flags[] = 'нет api_ai';
                $warnings++;
            } else {
                if ($apiAi->status !== ApiAiStatusEnum::Active) {
                    $flags[] = 'ИИ отключён';
                    $warnings++;
                }
                if ($apiSource !== ApiAiSourceEnum::YandexGTP4) {
                    $flags[] = 'не YandexGPT';
                    $warnings++;
                }
            }

            $statusLabel = $channel->status instanceof ApiChannelStatusEnum
                ? $channel->status->toString()
                : (string) $channel->status;
            $sourceLabel = $channel->channel_source instanceof ApiChannelSourceEnum
                ? $channel->channel_source->value
                : (string) $channel->channel_source;

            $rows[] = [
                $channel->id,
                $statusLabel,
                $sourceLabel,
                mb_substr((string) $channel->title, 0, 40),
                mb_substr((string) $channel->link, 0, 48),
                (string) ($channel->api_ai_id ?? '—'),
                $apiSourceLabel,
                $aiStatusLabel,
                $flags === [] ? '' : implode('; ', $flags),
            ];
        }

        $this->table(
            ['id', 'status', 'source', 'title', 'link', 'api_ai_id', 'api_source', 'ai_status', 'warnings'],
            $rows
        );

        if ($warnings > 0) {
            $this->warn("Предупреждений по ИИ/провайдеру: {$warnings} (провайдер не меняется автоматически).");
        }
    }
}
