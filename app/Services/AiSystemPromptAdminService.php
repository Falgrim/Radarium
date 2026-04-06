<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiDataTypeEnum;
use App\Models\AdminSystemPrompt;
use App\Models\ApiChannel;

final class AiSystemPromptAdminService
{
    public function getBody(AdminSystemPromptScope $scope): string
    {
        $row = AdminSystemPrompt::query()->where('scope', $scope->value)->first();

        return $row?->body ?? '';
    }

    public function saveBody(AdminSystemPromptScope $scope, string $body): void
    {
        AdminSystemPrompt::query()->updateOrCreate(
            ['scope' => $scope->value],
            ['body' => $body]
        );
    }

    /**
     * Копирует текст промпта в поле ai_promt всех источников с заданным типом выборки.
     *
     * @return int число обновлённых записей
     */
    public function applyToChannels(ApiDataTypeEnum $type, string $prompt): int
    {
        return ApiChannel::query()
            ->where('is_company', $type)
            ->update(['ai_promt' => $prompt]);
    }
}
