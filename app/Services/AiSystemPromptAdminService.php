<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\AdminSystemPromptPreset;
use App\Models\ApiAi;
use App\Models\ApiChannel;
use Illuminate\Support\Collection;

final class AiSystemPromptAdminService
{
    /**
     * @return Collection<int, AdminSystemPromptPreset>
     */
    public function listPresets(AdminSystemPromptScope $scope): Collection
    {
        return AdminSystemPromptPreset::query()
            ->where('scope', $scope->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findPresetForScope(AdminSystemPromptScope $scope, int $id): ?AdminSystemPromptPreset
    {
        return AdminSystemPromptPreset::query()
            ->where('scope', $scope->value)
            ->whereKey($id)
            ->first();
    }

    /**
     * Первым идёт «Все ИИ обработчики»; далее — конкретные провайдеры из активных «Сервисы ИИ» или весь перечень enum.
     *
     * @return array<string, string>
     */
    public function selectableAiSources(): array
    {
        $active = ApiAi::query()
            ->where('status', ApiAiStatusEnum::Active)
            ->pluck('api_source')
            ->map(static function ($source): string {
                return $source instanceof ApiAiSourceEnum ? $source->value : (string) $source;
            })
            ->unique()
            ->sort()
            ->values();

        $sourceValues = $active->isNotEmpty()
            ? $active->all()
            : array_map(static fn (ApiAiSourceEnum $e) => $e->value, ApiAiSourceEnum::cases());

        $specific = [];
        foreach ($sourceValues as $value) {
            $enum = ApiAiSourceEnum::tryFrom((string) $value);
            if ($enum !== null) {
                $specific[$enum->value] = (string) ($enum->toString() ?? $enum->value);
            }
        }

        return [
            AdminSystemPromptPreset::API_SOURCE_ALL_HANDLERS => 'Все ИИ обработчики',
        ] + $specific;
    }

    /**
     * @return list<string>
     */
    public function allowedApiSourceKeys(): array
    {
        return array_keys($this->selectableAiSources());
    }

    public function savePreset(
        AdminSystemPromptScope $scope,
        string $name,
        string $apiSource,
        string $body,
        ?int $presetId,
    ): AdminSystemPromptPreset {
        if ($presetId !== null) {
            $preset = $this->findPresetForScope($scope, $presetId);
            if ($preset === null) {
                abort(404);
            }
            $preset->update([
                'name' => $name,
                'api_source' => $apiSource,
                'body' => $body,
            ]);

            return $preset->fresh();
        }

        return AdminSystemPromptPreset::query()->create([
            'scope' => $scope->value,
            'name' => $name,
            'api_source' => $apiSource,
            'body' => $body,
        ]);
    }

    /**
     * Копирует текст пресета в поле ai_promt у источников с нужным типом выборки.
     * Если пресет привязан ко всем обработчикам — без фильтра по ИИ; иначе только каналы с соответствующим api_source у сервиса ИИ.
     *
     * @return int число обновлённых записей
     */
    public function applyPresetToChannels(
        AdminSystemPromptScope $scope,
        ApiDataTypeEnum $type,
        int $presetId,
    ): int {
        $preset = $this->findPresetForScope($scope, $presetId);
        if ($preset === null) {
            abort(404);
        }

        $prompt = (string) ($preset->body ?? '');

        $query = ApiChannel::query()->where('is_company', $type);

        if (! $preset->targetsAllAiHandlers()) {
            $query->whereHas('apiAi', static function ($q) use ($preset): void {
                $q->where('api_source', $preset->api_source);
            });
        }

        return $query->update(['ai_promt' => $prompt]);
    }

    /**
     * Пресет считается «текущим применённым», если есть хотя бы один подходящий источник и у всех них ai_promt совпадает с телом пресета.
     */
    public function isPresetCurrentlyApplied(ApiDataTypeEnum $type, AdminSystemPromptPreset $preset): bool
    {
        $body = (string) ($preset->body ?? '');

        $query = ApiChannel::query()
            ->where('is_company', $type);

        if (! $preset->targetsAllAiHandlers()) {
            $query->whereHas('apiAi', static function ($q) use ($preset): void {
                $q->where('api_source', $preset->api_source);
            });
        }

        $channels = $query->clone()->get(['ai_promt']);
        if ($channels->isEmpty()) {
            return false;
        }

        return $channels->every(static function ($channel) use ($body): bool {
            $current = $channel->ai_promt;
            if ($current === null) {
                $current = '';
            }

            return $current === $body;
        });
    }
}
