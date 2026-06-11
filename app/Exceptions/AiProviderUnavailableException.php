<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Временная недоступность AI-провайдера (сеть, таймаут, HTTP 502/503/504).
 * Пост должен остаться в очереди InQueue для повторной обработки.
 */
final class AiProviderUnavailableException extends \RuntimeException
{
}
