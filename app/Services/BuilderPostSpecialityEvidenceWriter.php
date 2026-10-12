<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BuilderPostSpecialityEvidence;
use Illuminate\Support\Facades\DB;

/**
 * Сохранение построчных доказательств специализаций для Complete-постов builders.
 */
final class BuilderPostSpecialityEvidenceWriter
{
    public function __construct(
        private readonly BuilderSpecialityMatcher $matcher,
    ) {
    }

    /**
     * @param  list<string>|null  $aiSpecialities
     * @return array{written: int, evidence: array<int, array{text_score: float, ai_score: float, combined: float}>}
     */
    public function writeForPost(int $apiChannelPostId, string $text, ?array $aiSpecialities, ?string $matcherVersion = null): array
    {
        $version = $matcherVersion ?? (string) config('author_speciality_profile.matcher_version', 'v2');
        $collected = $this->matcher->collectEvidence($text, $aiSpecialities);
        $byId = $collected['by_id'];

        DB::transaction(function () use ($apiChannelPostId, $version, $byId): void {
            BuilderPostSpecialityEvidence::query()
                ->where('api_channel_post_id', $apiChannelPostId)
                ->where('matcher_version', $version)
                ->delete();

            $now = now();
            $rows = [];
            foreach ($byId as $specId => $row) {
                if (($row['combined'] ?? 0) <= 0) {
                    continue;
                }
                $rows[] = [
                    'api_channel_post_id' => $apiChannelPostId,
                    'dictionary_speciality_id' => (int) $specId,
                    'text_score' => round((float) $row['text_score'], 4),
                    'ai_score' => round((float) $row['ai_score'], 4),
                    'combined' => round((float) $row['combined'], 4),
                    'matcher_version' => $version,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                BuilderPostSpecialityEvidence::query()->insert($rows);
            }
        });

        $evidence = [];
        foreach ($byId as $specId => $row) {
            $evidence[(int) $specId] = [
                'text_score' => (float) $row['text_score'],
                'ai_score' => (float) $row['ai_score'],
                'combined' => (float) $row['combined'],
            ];
        }

        return ['written' => count($evidence), 'evidence' => $evidence];
    }

    /**
     * @return list<string>
     */
    public static function extractAiSpecialitiesFromPostJson(mixed $aiResult): array
    {
        if (! is_string($aiResult) || $aiResult === '') {
            return [];
        }

        $decoded = json_decode($aiResult, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach (['specialities', 'service_types'] as $key) {
            if (! isset($decoded[$key]) || ! is_array($decoded[$key])) {
                continue;
            }
            foreach ($decoded[$key] as $v) {
                if (is_string($v) && $v !== '') {
                    $out[] = $v;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
