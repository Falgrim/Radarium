<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use App\Models\DictionarySpeciality;
use Illuminate\Support\Collection;

/**
 * Сопоставление текста поста и списка от LLM со справочником специализаций builders.
 * collectEvidence — построчные доказательства; resolve — обёртка для gate/legacy.
 */
final class BuilderSpecialityMatcher
{
    private const TEXT_MAX_LEN = 8000;

    /** Короткие токены: точное вхождение в нормализованном тексте (без границ слова). */
    private const array SHORT_TOKEN_WHITELIST = [
        'гкл', 'лстк', 'овик', 'скс', 'лвс', 'маф', 'жби', 'пвх', 'ппр', 'сип', 'кнауф',
    ];

    /** @var array<string, float>|null */
    private ?array $keywordIdfCache = null;

    public function __construct(
        private readonly ?Collection $dictionaryOverride = null,
    ) {
    }

    /**
     * Доказательства по посту: leaf-first, с IDF/weak/AI.
     *
     * @param  list<string>|null  $aiSpecialities
     * @return array{
     *   by_id: array<int, array{text_score: float, ai_score: float, combined: float, hits: list<string>, is_leaf: bool}>,
     *   scale: float,
     *   log: array<string, mixed>
     * }
     */
    public function collectEvidence(string $text, ?array $aiSpecialities = null): array
    {
        $dict = $this->dictionary();
        $normalized = $this->normalizeText($text);
        $idf = $this->keywordIdf($dict);
        $childIds = $this->childIdsByParent($dict);

        $byId = [];
        foreach ($dict as $item) {
            $id = (int) $item->id;
            $isLeaf = ! isset($childIds[$id]) || $childIds[$id] === [];
            $textScore = $this->scoreTextAgainstRow($normalized, $item, $idf);
            $byId[$id] = [
                'text_score' => $textScore,
                'ai_score' => 0.0,
                'combined' => 0.0,
                'hits' => $textScore > 0 ? ['text'] : [],
                'is_leaf' => $isLeaf,
            ];
        }

        $aiList = $aiSpecialities ?? [];
        $aiMatches = $this->scoreAiList($aiList, $dict);
        foreach ($aiMatches as $id => $aiScore) {
            if (! isset($byId[$id])) {
                continue;
            }
            $byId[$id]['ai_score'] = max($byId[$id]['ai_score'], $aiScore);
            if ($aiScore > 0 && ! in_array('ai', $byId[$id]['hits'], true)) {
                $byId[$id]['hits'][] = 'ai';
            }
        }

        foreach ($byId as $id => $row) {
            $t = min(1.0, max(0.0, $row['text_score']));
            $a = min(1.0, max(0.0, $row['ai_score']));
            $byId[$id]['text_score'] = $t;
            $byId[$id]['ai_score'] = $a;
            $byId[$id]['combined'] = 1.0 - (1.0 - $t) * (1.0 - $a);
        }

        // Leaf-first: обнулить группы, если есть лист с evidence у того же родителя или сама группа имеет детей с evidence.
        $leafEvidence = [];
        foreach ($byId as $id => $row) {
            if ($row['is_leaf'] && $row['combined'] > 0) {
                $leafEvidence[$id] = true;
            }
        }
        foreach ($byId as $id => $row) {
            if ($row['is_leaf'] || $row['combined'] <= 0) {
                continue;
            }
            $children = $childIds[$id] ?? [];
            $hasLeafChildEvidence = false;
            foreach ($children as $cid) {
                if (isset($leafEvidence[$cid])) {
                    $hasLeafChildEvidence = true;
                    break;
                }
            }
            if ($hasLeafChildEvidence) {
                $byId[$id]['combined'] = 0.0;
                $byId[$id]['text_score'] = 0.0;
                $byId[$id]['hits'][] = 'suppressed_parent';
            }
        }

        $broadMin = (float) config('author_speciality_profile.broad_post_combined_min', 0.25);
        $broadThreshold = (int) config('author_speciality_profile.broad_post_threshold', 4);
        $above = 0;
        foreach ($byId as $row) {
            if ($row['combined'] >= $broadMin) {
                ++$above;
            }
        }
        $scale = 1.0;
        if ($above > $broadThreshold) {
            $scale = 1.0 / sqrt((float) $above);
            foreach ($byId as $id => $row) {
                $byId[$id]['combined'] = round($row['combined'] * $scale, 4);
                $byId[$id]['text_score'] = round($row['text_score'] * $scale, 4);
                $byId[$id]['ai_score'] = round($row['ai_score'] * $scale, 4);
            }
        }

        $filtered = [];
        foreach ($byId as $id => $row) {
            if ($row['combined'] > 0) {
                $filtered[$id] = $row;
            }
        }

        return [
            'by_id' => $filtered,
            'scale' => $scale,
            'log' => [
                'text_len' => mb_strlen($normalized),
                'ai_count' => count($aiList),
                'above_broad_threshold' => $above,
                'scale' => $scale,
                'ids' => array_keys($filtered),
            ],
        ];
    }

    /**
     * @param  list<string>|null  $aiSpecialities  Значения как в JSON specialities (title из справочника).
     * @return array{ids: list<int>, log: array<string, mixed>}
     */
    public function resolve(string $text, ?array $aiSpecialities, ?int $maxSpecialityIds = null): array
    {
        $evidence = $this->collectEvidence($text, $aiSpecialities);
        $scores = [];
        foreach ($evidence['by_id'] as $id => $row) {
            $scores[(int) $id] = (float) $row['combined'];
        }

        arsort($scores);
        $ids = array_map('intval', array_keys($scores));

        if ($maxSpecialityIds !== null && $maxSpecialityIds > 0 && count($ids) > $maxSpecialityIds) {
            $ids = array_slice($ids, 0, $maxSpecialityIds);
        }

        $fromText = [];
        $fromAi = [];
        foreach ($evidence['by_id'] as $id => $row) {
            if (($row['text_score'] ?? 0) > 0) {
                $fromText[] = (int) $id;
            }
            if (($row['ai_score'] ?? 0) > 0) {
                $fromAi[] = (int) $id;
            }
        }

        return [
            'ids' => array_values($ids),
            'log' => [
                'text_match_ids' => $fromText,
                'ai_match_ids' => $fromAi,
                'merged_before_parents' => array_values($ids),
                'final_ids' => array_values($ids),
                'evidence_scale' => $evidence['scale'],
                'leaf_first' => true,
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function matchFromText(string $normalizedText, ?Collection $dictionary = null): array
    {
        $dict = $dictionary ?? $this->dictionary();
        $idf = $this->keywordIdf($dict);
        $ids = [];

        foreach ($dict as $item) {
            if ($this->scoreTextAgainstRow($normalizedText, $item, $idf) > 0) {
                $ids[] = (int) $item->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $aiSpecialities
     * @return list<int>
     */
    public function matchFromAiList(array $aiSpecialities, ?Collection $dictionary = null): array
    {
        $dict = $dictionary ?? $this->dictionary();
        $scores = $this->scoreAiList($aiSpecialities, $dict);

        return array_values(array_map('intval', array_keys($scores)));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function expandWithParents(array $ids, ?Collection $dictionary = null): array
    {
        $dict = $dictionary ?? $this->dictionary();
        $byId = $dict->keyBy('id');
        $out = $ids;

        foreach ($ids as $id) {
            $current = $byId->get($id);
            while ($current && $current->parent_id) {
                $pid = (int) $current->parent_id;
                $out[] = $pid;
                $current = $byId->get($pid);
            }
        }

        return array_values(array_unique(array_map('intval', $out)));
    }

    public function normalizeTextPublic(string $text): string
    {
        return $this->normalizeText($text);
    }

    private function dictionary(): Collection
    {
        if ($this->dictionaryOverride !== null) {
            return $this->dictionaryOverride;
        }

        return DictionarySpeciality::query()
            ->where('api_data_type_id', ApiDataTypeEnum::Builder)
            ->get();
    }

    /**
     * @return array<int, list<int>>
     */
    private function childIdsByParent(Collection $dict): array
    {
        $map = [];
        foreach ($dict as $item) {
            $pid = $item->parent_id !== null ? (int) $item->parent_id : null;
            if ($pid === null) {
                continue;
            }
            $map[$pid][] = (int) $item->id;
        }

        return $map;
    }

    /**
     * IDF по словарю листьев: log(N / df), N = число листьев.
     *
     * @return array<string, float>
     */
    private function keywordIdf(Collection $dict): array
    {
        if ($this->keywordIdfCache !== null && $this->dictionaryOverride === null) {
            return $this->keywordIdfCache;
        }

        $childIds = $this->childIdsByParent($dict);
        $df = [];
        $leafCount = 0;
        foreach ($dict as $item) {
            $id = (int) $item->id;
            $isLeaf = ! isset($childIds[$id]) || $childIds[$id] === [];
            if (! $isLeaf) {
                continue;
            }
            ++$leafCount;
            $seen = [];
            foreach ($this->rowKeywords($item, true) as $kw) {
                $norm = $this->normalizeFragment($kw);
                if ($norm === '' || isset($seen[$norm])) {
                    continue;
                }
                $seen[$norm] = true;
                $df[$norm] = ($df[$norm] ?? 0) + 1;
            }
            foreach ($this->rowWeakKeywords($item) as $kw) {
                $norm = $this->normalizeFragment($kw);
                if ($norm === '' || isset($seen[$norm])) {
                    continue;
                }
                $seen[$norm] = true;
                $df[$norm] = ($df[$norm] ?? 0) + 1;
            }
        }

        $n = max(1, $leafCount);
        $idf = [];
        foreach ($df as $kw => $count) {
            $idf[$kw] = log(($n + 1) / ((float) $count + 0.5));
        }

        if ($this->dictionaryOverride === null) {
            $this->keywordIdfCache = $idf;
        }

        return $idf;
    }

    /**
     * @param  array<string, float>  $idf
     */
    private function scoreTextAgainstRow(string $normalizedText, object $item, array $idf): float
    {
        if ($normalizedText === '') {
            return 0.0;
        }

        foreach ($this->rowNegativeKeywords($item) as $neg) {
            $negNorm = $this->normalizeFragment($neg);
            if ($negNorm !== '' && $this->keywordMatches($normalizedText, $negNorm)) {
                return 0.0;
            }
        }

        $raw = 0.0;
        $titleNorm = $this->normalizeFragment((string) ($item->title ?? ''));
        $titleHit = $titleNorm !== '' && $this->keywordMatches($normalizedText, $titleNorm);
        if ($titleHit) {
            $raw += 1.2;
        }

        $strongHits = 0;
        $weakHits = 0;
        foreach ($this->rowKeywords($item, true) as $kw) {
            $kwNorm = $this->normalizeFragment((string) $kw);
            if ($kwNorm === '' || ! $this->keywordMatches($normalizedText, $kwNorm)) {
                continue;
            }
            ++$strongHits;
            $w = $idf[$kwNorm] ?? 1.0;
            $raw += max(0.35, min(1.4, $w));
        }

        foreach ($this->rowWeakKeywords($item) as $kw) {
            $kwNorm = $this->normalizeFragment((string) $kw);
            if ($kwNorm === '' || ! $this->keywordMatches($normalizedText, $kwNorm)) {
                continue;
            }
            ++$weakHits;
            $w = ($idf[$kwNorm] ?? 0.6) * 0.45;
            $raw += max(0.15, min(0.6, $w));
        }

        // Слабые ключи без сильных и без title — только при 2+ разных слабых.
        if ($strongHits === 0 && ! $titleHit && $weakHits < 2) {
            return 0.0;
        }

        if ($raw <= 0) {
            return 0.0;
        }

        return 1.0 - exp(-$raw);
    }

    /**
     * @param  list<string>  $aiSpecialities
     * @return array<int, float> id => ai_score
     */
    private function scoreAiList(array $aiSpecialities, Collection $dict): array
    {
        $scores = [];
        $fuzzyMin = (float) config('author_speciality_profile.ai_fuzzy_percent_min', 90.0);
        $fuzzyFactor = (float) config('author_speciality_profile.ai_fuzzy_score_factor', 0.5);

        foreach (array_values($aiSpecialities) as $idx => $aiValue) {
            $aiLower = mb_strtolower(trim((string) $aiValue));
            if ($aiLower === '') {
                continue;
            }
            $aiNorm = $this->normalizeFragment($aiLower);
            if ($aiNorm === '') {
                continue;
            }

            $rankBoost = max(0.35, 1.0 - min(0.65, (float) $idx * 0.12));
            $bestId = null;
            $bestScore = 0.0;

            foreach ($dict as $item) {
                $id = (int) $item->id;
                $exact = $this->aiExactMatch($aiNorm, $item);
                if ($exact) {
                    $s = 1.0 * $rankBoost;
                    if ($s > $bestScore) {
                        $bestScore = $s;
                        $bestId = $id;
                    }
                    continue;
                }

                $fuzzy = $this->aiFuzzyMatchPercent($aiNorm, $item);
                if ($fuzzy >= $fuzzyMin) {
                    $s = $fuzzyFactor * ($fuzzy / 100.0) * $rankBoost;
                    if ($s > $bestScore) {
                        $bestScore = $s;
                        $bestId = $id;
                    }
                }
            }

            if ($bestId !== null) {
                $scores[$bestId] = max($scores[$bestId] ?? 0.0, $bestScore);
            }
        }

        return $scores;
    }

    private function aiExactMatch(string $aiNorm, object $item): bool
    {
        foreach ([$item->title ?? '', $item->short_name ?? ''] as $c) {
            $cNorm = $this->normalizeFragment((string) $c);
            if ($cNorm !== '' && $aiNorm === $cNorm) {
                return true;
            }
        }

        return false;
    }

    private function aiFuzzyMatchPercent(string $aiNorm, object $item): float
    {
        $best = 0.0;
        foreach ([$item->title ?? '', $item->short_name ?? ''] as $c) {
            $cNorm = $this->normalizeFragment((string) $c);
            if ($cNorm === '') {
                continue;
            }
            similar_text($aiNorm, $cNorm, $percent);
            $best = max($best, (float) $percent);
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function rowKeywords(object $item, bool $strongOnly): array
    {
        $kw = is_array($item->key_words ?? null) ? $item->key_words : [];
        $out = [];
        foreach ($kw as $k) {
            if (is_string($k) && $k !== '') {
                $out[] = $k;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function rowWeakKeywords(object $item): array
    {
        $kw = is_array($item->weak_key_words ?? null) ? $item->weak_key_words : [];
        $out = [];
        foreach ($kw as $k) {
            if (is_string($k) && $k !== '') {
                $out[] = $k;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function rowNegativeKeywords(object $item): array
    {
        $kw = is_array($item->negative_key_words ?? null) ? $item->negative_key_words : [];
        $out = [];
        foreach ($kw as $k) {
            if (is_string($k) && $k !== '') {
                $out[] = $k;
            }
        }

        return $out;
    }

    private function normalizeText(string $text): string
    {
        $t = mb_substr($text, 0, self::TEXT_MAX_LEN);
        $t = mb_strtolower($t);
        $t = str_replace(['ё'], ['е'], $t);
        $t = preg_replace('/[^\p{L}\p{N}+\\/\-]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    private function normalizeFragment(string $fragment): string
    {
        $f = mb_strtolower($fragment);
        $f = str_replace(['ё'], ['е'], $f);
        $f = preg_replace('/[^\p{L}\p{N}+\\/\-]+/u', ' ', $f) ?? $f;
        $f = preg_replace('/\s+/u', ' ', $f) ?? $f;

        return trim($f);
    }

    private function keywordMatches(string $normalizedText, string $keywordNorm): bool
    {
        if (mb_strlen($keywordNorm) < 3 && ! in_array($keywordNorm, self::SHORT_TOKEN_WHITELIST, true)) {
            return false;
        }

        if (in_array($keywordNorm, self::SHORT_TOKEN_WHITELIST, true)) {
            return str_contains($normalizedText, $keywordNorm);
        }

        return $this->wordBoundaryMatch($normalizedText, $keywordNorm);
    }

    private function wordBoundaryMatch(string $normalizedText, string $keywordNorm): bool
    {
        $quoted = preg_quote($keywordNorm, '/');
        $pattern = '/(?<![\p{L}\p{N}+\\/])'.$quoted.'(?:[\p{L}\p{N}+\\/]{0,4})?(?![\p{L}\p{N}+\\/])/u';

        return (bool) preg_match($pattern, $normalizedText);
    }
}
