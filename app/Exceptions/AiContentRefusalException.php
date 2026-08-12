<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Модель отказалась отвечать по политике безопасности (не JSON).
 * Пост помечается DontMatch — повторный прогон обычно бессмысленен.
 */
final class AiContentRefusalException extends \RuntimeException
{
}
