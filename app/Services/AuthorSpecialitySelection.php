<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Чистые правила отбора топ-специализаций автора из агрегированных scores.
 */
final class AuthorSpecialitySelection
{
    /**
     * @param  array<int, array{score: float, share: float, support: int}>  $agg  уже отсортировано по score desc
     * @return list<int>
     */
    public static function selectTop(array $agg, int $max, int $postCount, ?array $thresholds = null): array
    {
        if ($agg === [] || $max <= 0) {
            return [];
        }

        $primaryMin = (float) ($thresholds['primary_score_min'] ?? config('author_speciality_profile.primary_score_min', 0.20));
        $primaryFloor = (float) ($thresholds['primary_score_floor'] ?? config('author_speciality_profile.primary_score_floor', 0.15));
        $shareMin = (float) ($thresholds['secondary_share_min'] ?? config('author_speciality_profile.secondary_share_min', 0.20));
        $vsTop = (float) ($thresholds['secondary_vs_top_ratio'] ?? config('author_speciality_profile.secondary_vs_top_ratio', 0.35));
        $supportMin = (int) ($thresholds['secondary_support_min'] ?? config('author_speciality_profile.secondary_support_min', 2));
        $fewCombined = (float) ($thresholds['few_posts_combined_min'] ?? config('author_speciality_profile.few_posts_combined_min', 0.5));

        $ids = array_keys($agg);
        $topId = (int) $ids[0];
        $topScore = (float) $agg[$topId]['score'];

        if ($topScore < $primaryMin) {
            $fewOk = $postCount <= 2 && $topScore >= $fewCombined * 0.35;
            // support часто 0 при combined поста < support_combined_min — floor смотрит только на score.
            $floorOk = $topScore >= $primaryFloor;
            if (! $fewOk && ! $floorOk) {
                return [];
            }
        }

        $selected = [$topId];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id === $topId) {
                continue;
            }
            if (count($selected) >= $max) {
                break;
            }
            $row = $agg[$id];
            $okShare = $row['share'] >= $shareMin;
            $okRatio = $row['score'] >= $vsTop * $topScore;
            $okSupport = $postCount <= 2
                ? $row['score'] >= $fewCombined * 0.35
                : $row['support'] >= $supportMin;

            if ($okShare && $okRatio && $okSupport) {
                $selected[] = $id;
            }
        }

        return array_values($selected);
    }

    /**
     * Вес поста: recency * dup-представитель.
     *
     * @param  array<int, string>  $postIdToHash
     * @param  array<int, float>  $postIdToAgeMonths
     * @return array<int, float>
     */
    public static function postWeights(
        array $postIdToHash,
        array $postIdToAgeMonths,
        float $halfLifeMonths = 12.0,
        float $minRecency = 0.3,
    ): array {
        $halfLifeMonths = max(0.1, $halfLifeMonths);
        $dupCounts = array_count_values($postIdToHash);
        $meta = [];
        foreach ($postIdToHash as $postId => $hash) {
            $n = (int) ($dupCounts[$hash] ?? 1);
            $isRep = true;
            foreach ($postIdToHash as $otherId => $otherHash) {
                if ($otherHash === $hash && $otherId < $postId) {
                    $isRep = false;
                    break;
                }
            }
            $dup = $isRep ? (1.0 + log(1.0 + max(0, $n - 1))) : 0.0;
            $months = max(0.0, (float) ($postIdToAgeMonths[$postId] ?? 0.0));
            $recency = max($minRecency, pow(0.5, $months / $halfLifeMonths));
            $meta[$postId] = $recency * $dup;
        }

        return $meta;
    }
}
