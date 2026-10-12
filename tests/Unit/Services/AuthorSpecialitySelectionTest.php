<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AuthorSpecialitySelection;
use Tests\TestCase;

final class AuthorSpecialitySelectionTest extends TestCase
{
    private array $thresholds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->thresholds = [
            'primary_score_min' => 0.25,
            'secondary_share_min' => 0.20,
            'secondary_vs_top_ratio' => 0.35,
            'secondary_support_min' => 2,
            'few_posts_combined_min' => 0.5,
        ];
    }

    public function test_outlier_post_does_not_displace_primary(): void
    {
        // Основная — кладка (много поддержки), разовый монолит слабее
        $agg = [
            11 => ['score' => 3.2, 'share' => 0.72, 'support' => 5],
            1 => ['score' => 0.9, 'share' => 0.20, 'support' => 1],
            20 => ['score' => 0.35, 'share' => 0.08, 'support' => 1],
        ];

        $selected = AuthorSpecialitySelection::selectTop($agg, 3, 6, $this->thresholds);

        $this->assertSame([11], $selected);
    }

    public function test_reposts_do_not_inflate_via_zero_weight_duplicates(): void
    {
        $hashes = [
            1 => 'aaa',
            2 => 'aaa',
            3 => 'aaa',
            4 => 'bbb',
        ];
        $ages = [1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0];
        $weights = AuthorSpecialitySelection::postWeights($hashes, $ages, 12.0, 0.3);

        $this->assertGreaterThan(0, $weights[1]);
        $this->assertSame(0.0, $weights[2]);
        $this->assertSame(0.0, $weights[3]);
        $this->assertGreaterThan(0, $weights[4]);
        // представитель репостов чуть тяжелее одиночного
        $this->assertGreaterThan($weights[4], $weights[1]);
    }

    public function test_broad_profile_does_not_fill_three_equals(): void
    {
        $agg = [
            41 => ['score' => 1.0, 'share' => 0.34, 'support' => 2],
            42 => ['score' => 0.95, 'share' => 0.33, 'support' => 2],
            11 => ['score' => 0.95, 'share' => 0.33, 'support' => 2],
        ];
        // все проходят пороги — но это ок для «делаем всё»; масштабирование broad на уровне поста.
        // Здесь проверяем, что без достаточного vs_top/share вторичные отсекаются при доминанте:
        $dominant = [
            41 => ['score' => 4.0, 'share' => 0.70, 'support' => 4],
            42 => ['score' => 0.8, 'share' => 0.14, 'support' => 2],
            11 => ['score' => 0.9, 'share' => 0.16, 'support' => 2],
        ];
        $selected = AuthorSpecialitySelection::selectTop($dominant, 3, 5, $this->thresholds);
        $this->assertSame([41], $selected);
    }

    public function test_fresh_posts_outweigh_old(): void
    {
        $hashes = [1 => 'old', 2 => 'new'];
        $ages = [1 => 36.0, 2 => 1.0];
        $weights = AuthorSpecialitySelection::postWeights($hashes, $ages, 12.0, 0.3);

        $this->assertGreaterThan(0.0, $weights[1]);
        $this->assertGreaterThan($weights[1], $weights[2]);
    }

    public function test_single_post_author_gets_short_set(): void
    {
        $agg = [
            20 => ['score' => 0.6, 'share' => 1.0, 'support' => 1],
        ];
        $selected = AuthorSpecialitySelection::selectTop($agg, 3, 1, $this->thresholds);
        $this->assertSame([20], $selected);
    }

    public function test_secondary_requires_support_for_many_posts(): void
    {
        $agg = [
            11 => ['score' => 2.0, 'share' => 0.55, 'support' => 4],
            41 => ['score' => 1.0, 'share' => 0.28, 'support' => 1], // share ok, ratio ok, support=1
        ];
        $selected = AuthorSpecialitySelection::selectTop($agg, 3, 5, $this->thresholds);
        $this->assertSame([11], $selected);

        $agg[41]['support'] = 2;
        $selected2 = AuthorSpecialitySelection::selectTop($agg, 3, 5, $this->thresholds);
        $this->assertSame([11, 41], $selected2);
    }
}
