<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use Illuminate\Support\Facades\Log;

/**
 * Решение статуса публичности после AI и программной обработки (post-filter перед каталогом).
 */
final class CatalogPublicationGate
{
    /**
     * @param  array<int|string>  $mergedSpecialityIds  id из словаря специализаций после матчингa по тексту+AI.
     * @param  array<string, mixed>  $logContext  api_channel_post_id, api_channel_id и т.д. для журнала.
     * @return array{status: ApiPostAiStatusEnum, reasons: list<string>}
     */
    public function decide(ApiDataTypeEnum $domain, string $postText, array $mergedSpecialityIds, array $logContext = []): array
    {
        if (! config('catalog_publication_gate.enabled', true)) {
            return [
                'status' => ApiPostAiStatusEnum::Active,
                'reasons' => [],
            ];
        }

        $reasons = [];

        if (config('catalog_publication_gate.require_matched_speciality', true) && count($mergedSpecialityIds) === 0) {
            $reasons[] = 'no_dictionary_speciality_matched';
        }

        if ($domain === ApiDataTypeEnum::Builder && config('catalog_publication_gate.builder_non_service_heuristics', true)) {
            foreach ((new CatalogPublicationBuilderNonServiceSignals)->reasons($postText) as $code) {
                if (! in_array($code, $reasons, true)) {
                    $reasons[] = $code;
                }
            }
        }

        if ($reasons !== []) {
            Log::channel('catalog_publication_gate')->info('Каталог: запись без авто-публикации (модерация)', array_merge([
                'domain' => $domain->value,
                'processed_at' => now()->format('Y-m-d H:i:s'),
                'post_text_preview' => $this->preview($postText),
                'merged_specialities_count' => count($mergedSpecialityIds),
                'reasons' => $reasons,
            ], $logContext));

            return [
                'status' => ApiPostAiStatusEnum::InModeration,
                'reasons' => $reasons,
            ];
        }

        return [
            'status' => ApiPostAiStatusEnum::Active,
            'reasons' => [],
        ];
    }

    private function preview(string $text, int $max = 2000): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max).'…';
    }
}
