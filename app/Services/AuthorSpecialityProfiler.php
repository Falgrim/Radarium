<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\DictionaryEnum;
use App\Models\AuthorSpecialityScore;
use App\Models\Builder;
use App\Models\BuilderPostSpecialityEvidence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Агрегация evidence постов автора → топ специализаций (до N листьев) с порогами уверенности.
 */
final class AuthorSpecialityProfiler
{
    public function __construct(
        private readonly Dictionary $dictionary,
        private readonly BuilderSpecialityMatcher $matcher,
        private readonly BuilderPostSpecialityEvidenceWriter $evidenceWriter,
    ) {
    }

    /**
     * @return array{
     *   changed: bool,
     *   builder_ids: list<int>,
     *   top_speciality_ids: list<int>,
     *   scores: list<array<string, mixed>>,
     *   shadow: bool
     * }
     */
    public function profileBuildersForUser(
        int $apiPostUserId,
        ?int $maxSpecialities = null,
        bool $dryRun = false,
        bool $ensureEvidence = true,
    ): array {
        $max = $maxSpecialities ?? (int) config('author_speciality_profile.max_specialities', AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR);
        $max = max(1, $max);
        $version = (string) config('author_speciality_profile.matcher_version', 'v2');
        $shadow = (bool) config('author_speciality_profile.shadow', false);

        $builders = Builder::query()
            ->where('api_post_user_id', $apiPostUserId)
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with(['post' => static function ($q): void {
                $q->select('id', 'post', 'ai_result', 'api_post_user_id', 'post_date', 'ai_parse_status');
            }])
            ->orderByDesc('id')
            ->get();

        if ($builders->isEmpty()) {
            return [
                'changed' => false,
                'builder_ids' => [],
                'top_speciality_ids' => [],
                'scores' => [],
                'shadow' => $shadow,
            ];
        }

        $posts = [];
        foreach ($builders as $builder) {
            $post = $builder->post;
            if (! $post) {
                continue;
            }
            $posts[(int) $post->id] = $post;
        }

        if ($ensureEvidence && ! $dryRun) {
            foreach ($posts as $post) {
                $existing = BuilderPostSpecialityEvidence::query()
                    ->where('api_channel_post_id', (int) $post->id)
                    ->where('matcher_version', $version)
                    ->exists();
                if ($existing) {
                    continue;
                }
                $ai = BuilderPostSpecialityEvidenceWriter::extractAiSpecialitiesFromPostJson($post->ai_result);
                $this->evidenceWriter->writeForPost(
                    (int) $post->id,
                    (string) ($post->post ?? ''),
                    $ai === [] ? null : $ai,
                    $version
                );
            }
        } elseif ($ensureEvidence && $dryRun) {
            // dry-run: считаем evidence в памяти, не пишем
        }

        $postIds = array_keys($posts);
        $evidenceRows = BuilderPostSpecialityEvidence::query()
            ->whereIn('api_channel_post_id', $postIds)
            ->where('matcher_version', $version)
            ->get()
            ->groupBy('api_channel_post_id');

        // Если dry-run и нет строк — собрать в памяти
        $memoryEvidence = [];
        if ($evidenceRows->isEmpty() || $dryRun) {
            foreach ($posts as $postId => $post) {
                if (! $dryRun && $evidenceRows->has($postId)) {
                    continue;
                }
                $ai = BuilderPostSpecialityEvidenceWriter::extractAiSpecialitiesFromPostJson($post->ai_result);
                $collected = $this->matcher->collectEvidence((string) ($post->post ?? ''), $ai === [] ? null : $ai);
                $memoryEvidence[$postId] = $collected['by_id'];
            }
        }

        $postMeta = $this->buildPostWeights($posts);
        $agg = $this->aggregateScores($posts, $evidenceRows, $memoryEvidence, $postMeta);
        $selected = AuthorSpecialitySelection::selectTop($agg, $max, count($posts));

        $scoreRows = [];
        $rank = 1;
        foreach ($agg as $specId => $row) {
            $scoreRows[] = [
                'dictionary_speciality_id' => (int) $specId,
                'score' => round((float) $row['score'], 4),
                'share' => round((float) $row['share'], 4),
                'support_posts' => (int) $row['support'],
                'rank' => in_array((int) $specId, $selected, true) ? $rank++ : 0,
                'is_selected' => in_array((int) $specId, $selected, true),
                'last_evidence_at' => $row['last_at'] ?? null,
            ];
        }
        // Перенумеровать selected по порядку selected
        $rankMap = [];
        foreach ($selected as $i => $sid) {
            $rankMap[$sid] = $i + 1;
        }
        foreach ($scoreRows as &$sr) {
            $sid = (int) $sr['dictionary_speciality_id'];
            if (isset($rankMap[$sid])) {
                $sr['rank'] = $rankMap[$sid];
                $sr['is_selected'] = true;
            } else {
                $sr['rank'] = 0;
                $sr['is_selected'] = false;
            }
        }
        unset($sr);
        usort($scoreRows, static fn (array $a, array $b): int => ($b['is_selected'] <=> $a['is_selected'])
            ?: (($a['rank'] ?: 999) <=> ($b['rank'] ?: 999))
            ?: ($b['score'] <=> $a['score']));

        $builderIds = $builders->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $changed = false;

        if (! $dryRun) {
            $this->persistScores($apiPostUserId, $scoreRows, $version);

            if (! $shadow) {
                foreach ($builders as $builder) {
                    $old = $builder->specialities()
                        ->pluck('dictionary_speciality_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->sort()
                        ->values()
                        ->all();
                    $new = $selected;
                    sort($new);
                    if ($old !== $new) {
                        $changed = true;
                    }
                    $this->dictionary->updateRelations(
                        DictionaryEnum::Speciality,
                        'builder',
                        (int) $builder->id,
                        $selected
                    );
                }
            } else {
                // shadow: только scores
                $changed = false;
            }
        } else {
            foreach ($builders as $builder) {
                $old = $builder->specialities()
                    ->pluck('dictionary_speciality_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                $new = $selected;
                sort($new);
                if ($old !== $new) {
                    $changed = true;
                }
            }
        }

        Log::channel('ai_debug')->info('[AuthorSpecialityProfiler] profileBuildersForUser', [
            'api_post_user_id' => $apiPostUserId,
            'dry_run' => $dryRun,
            'shadow' => $shadow,
            'max' => $max,
            'posts' => count($posts),
            'selected' => $selected,
            'top_scores' => array_slice($scoreRows, 0, 5),
            'changed' => $changed,
        ]);

        return [
            'changed' => $changed,
            'builder_ids' => $builderIds,
            'top_speciality_ids' => $selected,
            'scores' => $scoreRows,
            'shadow' => $shadow,
        ];
    }

    /**
     * @param  Collection<int, \App\Models\ApiChannelPost>  $posts
     * @return array<int, array{weight: float, hash: string, post_date: ?\Carbon\CarbonInterface}>
     */
    private function buildPostWeights(array $posts): array
    {
        $halfLife = max(0.1, (float) config('author_speciality_profile.recency_half_life_months', 12));
        $minW = max(0.0, (float) config('author_speciality_profile.recency_min_weight', 0.3));
        $now = now();

        $hashes = [];
        $ages = [];
        foreach ($posts as $postId => $post) {
            $norm = $this->matcher->normalizeTextPublic((string) ($post->post ?? ''));
            $hashes[$postId] = hash('sha256', $norm !== '' ? $norm : ('empty:'.$postId));
            $postDate = $post->post_date ?? null;
            $ages[$postId] = $postDate
                ? max(0.0, $postDate->diffInDays($now) / 30.437)
                : 0.0;
        }

        $weights = AuthorSpecialitySelection::postWeights($hashes, $ages, $halfLife, $minW);
        $meta = [];
        foreach ($posts as $postId => $post) {
            $meta[$postId] = [
                'weight' => (float) ($weights[$postId] ?? 0.0),
                'hash' => $hashes[$postId],
                'post_date' => $post->post_date ?? null,
            ];
        }

        return $meta;
    }

    /**
     * @param  array<int, \App\Models\ApiChannelPost>  $posts
     * @param  Collection<int, Collection<int, BuilderPostSpecialityEvidence>>  $evidenceRows
     * @param  array<int, array<int, array{text_score: float, ai_score: float, combined: float}>>  $memoryEvidence
     * @param  array<int, array{weight: float, hash: string, post_date: mixed}>  $postMeta
     * @return array<int, array{score: float, share: float, support: int, last_at: mixed}>
     */
    private function aggregateScores(array $posts, Collection $evidenceRows, array $memoryEvidence, array $postMeta): array
    {
        $supportMin = (float) config('author_speciality_profile.support_combined_min', 0.3);
        $scores = [];
        $support = [];
        $lastAt = [];

        foreach ($posts as $postId => $post) {
            $w = (float) ($postMeta[$postId]['weight'] ?? 0.0);
            if ($w <= 0) {
                continue;
            }

            $rows = [];
            if (isset($memoryEvidence[$postId])) {
                foreach ($memoryEvidence[$postId] as $specId => $ev) {
                    $rows[(int) $specId] = (float) $ev['combined'];
                }
            } elseif ($evidenceRows->has($postId)) {
                foreach ($evidenceRows->get($postId) as $evRow) {
                    $rows[(int) $evRow->dictionary_speciality_id] = (float) $evRow->combined;
                }
            }

            foreach ($rows as $specId => $combined) {
                if ($combined <= 0) {
                    continue;
                }
                $scores[$specId] = ($scores[$specId] ?? 0.0) + $w * $combined;
                if ($combined >= $supportMin) {
                    $support[$specId] = ($support[$specId] ?? 0) + 1;
                }
                $pd = $postMeta[$postId]['post_date'] ?? null;
                if ($pd && (! isset($lastAt[$specId]) || $pd > $lastAt[$specId])) {
                    $lastAt[$specId] = $pd;
                }
            }
        }

        $sum = array_sum($scores);
        $out = [];
        foreach ($scores as $specId => $s) {
            $out[$specId] = [
                'score' => $s,
                'share' => $sum > 0 ? $s / $sum : 0.0,
                'support' => (int) ($support[$specId] ?? 0),
                'last_at' => $lastAt[$specId] ?? null,
            ];
        }

        uasort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $scoreRows
     */
    private function persistScores(int $apiPostUserId, array $scoreRows, string $version): void
    {
        DB::transaction(function () use ($apiPostUserId, $scoreRows, $version): void {
            AuthorSpecialityScore::query()
                ->where('api_post_user_id', $apiPostUserId)
                ->where('matcher_version', $version)
                ->delete();

            $now = now();
            $insert = [];
            foreach ($scoreRows as $row) {
                $insert[] = [
                    'api_post_user_id' => $apiPostUserId,
                    'dictionary_speciality_id' => (int) $row['dictionary_speciality_id'],
                    'score' => $row['score'],
                    'share' => $row['share'],
                    'support_posts' => $row['support_posts'],
                    'rank' => $row['rank'],
                    'is_selected' => $row['is_selected'] ? 1 : 0,
                    'last_evidence_at' => $row['last_evidence_at'],
                    'computed_at' => $now,
                    'matcher_version' => $version,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($insert !== []) {
                // Храним топ-15 для объяснимости
                AuthorSpecialityScore::query()->insert(array_slice($insert, 0, 15));
            }
        });
    }
}
