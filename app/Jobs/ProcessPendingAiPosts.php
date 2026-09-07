<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

final class ProcessPendingAiPosts implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly ApiDataTypeEnum $dataType,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->dataType->value;
    }

    public function handle(): void
    {
        // Порядок обязан совпадать с выборкой в app:ai_parse:*, иначе партия останется
        // необработанной и проверка прогресса ниже остановит цепочку.
        $batchIds = $this->pendingQuery()
            ->orderBy(
                'api_channel_posts.post_date',
                $this->dataType === ApiDataTypeEnum::Specialist ? 'asc' : 'desc'
            )
            ->limit(100)
            ->pluck('api_channel_posts.id');

        if ($batchIds->isEmpty()) {
            return;
        }

        $exitCode = Artisan::call($this->command());
        $batchPendingAfter = $this->pendingQuery()
            ->whereIn('api_channel_posts.id', $batchIds)
            ->count();

        if ($exitCode !== 0 || $batchPendingAfter >= $batchIds->count()) {
            Log::warning('Фоновая обработка очереди ИИ остановлена: нет прогресса', [
                'data_type' => $this->dataType->name,
                'command' => $this->command(),
                'exit_code' => $exitCode,
                'batch_size' => $batchIds->count(),
                'batch_pending_after' => $batchPendingAfter,
                'output' => Artisan::output(),
            ]);

            return;
        }

        if ($this->pendingQuery()->exists()) {
            self::dispatch($this->dataType)->delay(now()->addSecond());
        }
    }

    private function pendingQuery(): Builder
    {
        return ApiChannelPost::query()
            ->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', $this->dataType)
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue);
    }

    private function command(): string
    {
        return match ($this->dataType) {
            ApiDataTypeEnum::Specialist => 'app:ai_parse:specialist',
            ApiDataTypeEnum::Builder => 'app:ai_parse:builder',
            ApiDataTypeEnum::Company => 'app:ai_parse:company',
        };
    }
}
