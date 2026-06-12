<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Exceptions\AiProviderUnavailableException;
use App\Models\ApiChannelPost;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

final class AiParsePostExceptionHandler
{
    public function handle(
        ApiChannelPost $post,
        \Throwable $e,
        ?object $aiService,
        Command $command,
    ): void {
        if ($this->isProviderUnavailable($e)) {
            if ($aiService !== null && method_exists($aiService, 'logging')) {
                $aiService->logging($e->getMessage(), true);
            }

            [$providerLabel, $endpoint] = $this->resolveProviderContext($aiService);
            app(AiProviderHealthAlertService::class)->notifyUnavailable(
                $providerLabel,
                $endpoint,
                $e->getMessage(),
            );

            $command->warn(sprintf(
                'ИИ временно недоступен — пост %d остаётся в очереди (InQueue): %s',
                $post->id,
                $e->getMessage()
            ));

            return;
        }

        $command->error($e->getMessage());

        if ($aiService !== null && method_exists($aiService, 'logging')) {
            $aiService->logging($e->getMessage(), true);
        }

        $post->ai_result = $e->getMessage();
        $post->ai_date = now();
        $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
        $post->save();
    }

    public function isProviderUnavailable(\Throwable $e): bool
    {
        if ($e instanceof AiProviderUnavailableException || $e instanceof ConnectionException) {
            return true;
        }

        $previous = $e->getPrevious();

        return $previous !== null && $this->isProviderUnavailable($previous);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveProviderContext(?object $aiService): array
    {
        if ($aiService instanceof ApiAIOllama) {
            return ['Ollama Qwen', $aiService->getHost()];
        }

        if ($aiService instanceof ApiAIYandex) {
            return ['Yandex GPT', 'yandex.cloud'];
        }

        return ['ИИ', 'unknown'];
    }
}
