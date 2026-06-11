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
}
