<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiAiSourceEnum;

/**
 * Фабрика ИИ-провайдеров для обработки постов builders.
 *
 * Вынесена, чтобы в Feature-тестах можно было подменить реализацию через контейнер
 * (bind BuilderAiProviderFactory → фейк).
 */
class BuilderAiProviderFactory
{
    public function make(ApiAiSourceEnum $source): object
    {
        return match ($source) {
            ApiAiSourceEnum::YandexGTP4 => new ApiAIYandex,
            ApiAiSourceEnum::OllamaQwen => new ApiAIOllama,
        };
    }
}
