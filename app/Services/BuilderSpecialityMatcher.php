<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use App\Models\DictionarySpeciality;
use Illuminate\Support\Collection;

/**
 * Сопоставление текста поста и списка от LLM со справочником специализаций builders.
 */
final class BuilderSpecialityMatcher
{
    private const TEXT_MAX_LEN = 8000;

    /** Короткие токены: точное вхождение в нормализованном тексте (без границ слова). */
    private const array SHORT_TOKEN_WHITELIST = [
        'гкл', 'лстк', 'овик', 'скс', 'лвс', 'маф', 'жби', 'пвх', 'ппр', 'сип', 'кнауф',
    ];

    public function __construct(
        private readonly ?Collection $dictionaryOverride = null,
    ) {
    }

    /**
     * @param  list<string>|null  $aiSpecialities  Значения как в JSON specialities (title из справочника).
     * @return array{ids: list<int>, log: array<string, mixed>}
     */
    public function resolve(string $text, ?array $aiSpecialities): array
    {
        $dict = $this->dictionary();
        $normalized = $this->normalizeText($text);

        $fromText = $this->matchFromText($normalized, $dict);
        $fromAi = $this->matchFromAiList($aiSpecialities ?? [], $dict);

        $merged = array_values(array_unique(array_merge($fromText, $fromAi)));
        $expanded = $this->expandWithParents($merged, $dict);
        $final = array_values(array_unique($expanded));

        return [
            'ids' => $final,
            'log' => [
                'text_match_ids' => $fromText,
                'ai_match_ids' => $fromAi,
                'merged_before_parents' => $merged,
                'final_ids' => $final,
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function matchFromText(string $normalizedText, ?Collection $dictionary = null): array
    {
        $dict = $dictionary ?? $this->dictionary();
        $ids = [];

        foreach ($dict as $item) {
            $keywords = is_array($item->key_words) ? $item->key_words : [];
            $titleNorm = $this->normalizeFragment($item->title ?? '');
            if ($titleNorm !== '' && $this->textContainsPhrase($normalizedText, $titleNorm)) {
                $ids[] = (int) $item->id;
                continue;
            }
            foreach ($keywords as $kw) {
                $kwNorm = $this->normalizeFragment((string) $kw);
                if ($kwNorm === '') {
                    continue;
                }
                if ($this->keywordMatches($normalizedText, $kwNorm)) {
                    $ids[] = (int) $item->id;
                    break;
                }
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
        $matchIds = [];

        foreach ($aiSpecialities as $aiValue) {
            $aiLower = mb_strtolower(trim((string) $aiValue));
            if ($aiLower === '') {
                continue;
            }

            $aiNorm = $this->normalizeFragment($aiLower);

            foreach ($dict as $item) {
                if ($this->aiMatchesRow($aiNorm, $item)) {
                    $matchIds[] = (int) $item->id;
                    break;
                }
            }
        }

        return array_values(array_unique($matchIds));
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

    private function dictionary(): Collection
    {
        if ($this->dictionaryOverride !== null) {
            return $this->dictionaryOverride;
        }

        return DictionarySpeciality::query()
            ->where('api_data_type_id', ApiDataTypeEnum::Builder)
            ->get();
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

    private function textContainsPhrase(string $normalizedText, string $phrase): bool
    {
        if ($phrase === '') {
            return false;
        }

        return str_contains($normalizedText, $phrase);
    }

    private function aiMatchesRow(string $aiNorm, object $item): bool
    {
        $titleNorm = $this->normalizeFragment((string) $item->title);
        $shortNorm = $this->normalizeFragment((string) ($item->short_name ?? ''));

        foreach ([$titleNorm, $shortNorm] as $c) {
            if ($c === '') {
                continue;
            }
            if ($this->aiStringMatchesCandidate($aiNorm, $c)) {
                return true;
            }
        }

        if (in_array($aiNorm, self::SHORT_TOKEN_WHITELIST, true)) {
            $keywords = is_array($item->key_words) ? $item->key_words : [];
            foreach ($keywords as $kw) {
                if ($this->normalizeFragment((string) $kw) === $aiNorm) {
                    return true;
                }
            }
        }

        $keywords = is_array($item->key_words) ? $item->key_words : [];
        foreach ($keywords as $kw) {
            $kwNorm = $this->normalizeFragment((string) $kw);
            if ($kwNorm === '') {
                continue;
            }
            if ($this->aiStringMatchesCandidate($aiNorm, $kwNorm)) {
                return true;
            }
        }

        return false;
    }

    private function aiStringMatchesCandidate(string $aiNorm, string $candidateNorm): bool
    {
        if ($aiNorm === '' || $candidateNorm === '') {
            return false;
        }

        if ($aiNorm === $candidateNorm) {
            return true;
        }

        similar_text($aiNorm, $candidateNorm, $percent);
        if ($percent >= 82.0) {
            return true;
        }

        $minSub = min(mb_strlen($aiNorm), mb_strlen($candidateNorm));
        if ($minSub >= 6 && (str_contains($candidateNorm, $aiNorm) || str_contains($aiNorm, $candidateNorm))) {
            similar_text($aiNorm, $candidateNorm, $p2);

            return $p2 >= 70.0;
        }

        return false;
    }
}
