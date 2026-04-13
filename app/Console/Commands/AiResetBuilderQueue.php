<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use Illuminate\Console\Command;

class AiResetBuilderQueue extends Command
{
    protected $signature = 'app:ai_parse:reset-builder-queue
                            {from : Начало периода (Y-m-d), например 2026-04-06}
                            {to : Конец периода включительно (Y-m-d), например 2026-04-13}
                            {--date-field=post_date : post_date — по дате сообщения; ai_date — по дате последнего запроса к ИИ}
                            {--dry-run : Показать количество без изменений в БД}
                            {--clear-metadata : Обнулить ai_result, ai_date, ai_provider_used (иначе старый ответ ИИ останется до нового прогона)}';

    protected $description = 'Вернуть посты каналов типа «Строитель» в очередь ИИ (InQueue) для повторной обработки';

    public function handle(): int
    {
        $dateField = (string) $this->option('date-field');
        if (! in_array($dateField, ['post_date', 'ai_date'], true)) {
            $this->error('Параметр --date-field должен быть post_date или ai_date.');

            return self::FAILURE;
        }

        $from = $this->argument('from') . ' 00:00:00';
        $to = $this->argument('to') . ' 23:59:59';

        $requeueStatuses = [
            ApiChannelPostStatusEnum::Complete,
            ApiChannelPostStatusEnum::Error,
            ApiChannelPostStatusEnum::DontMatch,
            ApiChannelPostStatusEnum::Empty,
        ];

        $query = ApiChannelPost::query()
            ->select('api_channel_posts.id')
            ->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Builder)
            ->whereBetween('api_channel_posts.' . $dateField, [$from, $to])
            ->whereIn('api_channel_posts.ai_parse_status', $requeueStatuses);

        $ids = $query->pluck('api_channel_posts.id');
        $count = $ids->count();

        if ($this->option('dry-run')) {
            $this->info("Записей для возврата в очередь: {$count} (поле даты: {$dateField}, период {$this->argument('from')} — {$this->argument('to')}).");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Нет записей, подходящих под условия.');

            return self::SUCCESS;
        }

        $payload = [
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue->value,
            'updated_at' => now(),
        ];

        if ($this->option('clear-metadata')) {
            $payload['ai_result'] = null;
            $payload['ai_date'] = null;
            $payload['ai_provider_used'] = null;
        }

        foreach ($ids->chunk(500) as $chunk) {
            ApiChannelPost::query()->whereIn('id', $chunk->all())->update($payload);
        }

        $this->info("Обновлено записей: {$count}. Запустите обработку: php artisan app:ai_parse:builder (или дождитесь планировщика).");

        return self::SUCCESS;
    }
}
