<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CatalogRegionBuckets;
use App\Support\CatalogRegionFilter;
use PHPUnit\Framework\TestCase;

class CatalogRegionFilterTest extends TestCase
{
    public function test_aggregated_dropdown_has_three_buckets(): void
    {
        [$list, $showNone] = CatalogRegionFilter::dropdownForAggregated();

        $this->assertFalse($showNone);
        $this->assertSame([
            CatalogRegionBuckets::FILTER_MOSCOW => CatalogRegionBuckets::LABEL_MOSCOW,
            CatalogRegionBuckets::FILTER_SPB => CatalogRegionBuckets::LABEL_SPB,
            CatalogRegionBuckets::FILTER_EXCEPT_MSK_SPB => CatalogRegionBuckets::LABEL_EXCEPT_MSK_SPB,
        ], $list);
    }

    public function test_allowed_values_aggregated(): void
    {
        [$list, $showNone] = CatalogRegionFilter::dropdownForAggregated();
        $allowed = CatalogRegionFilter::allowedRequestValues($list, $showNone);

        $this->assertContains('', $allowed);
        $this->assertContains(CatalogRegionBuckets::FILTER_MOSCOW, $allowed);
        $this->assertContains(CatalogRegionBuckets::FILTER_SPB, $allowed);
        $this->assertContains(CatalogRegionBuckets::FILTER_EXCEPT_MSK_SPB, $allowed);
        $this->assertNotContains('none', $allowed);
    }

    public function test_moscow_and_spb_canonical_helpers(): void
    {
        $this->assertTrue(CatalogRegionBuckets::isMoscowCanonical('Москва'));
        $this->assertTrue(CatalogRegionBuckets::isMoscowCanonical('Московская область'));
        $this->assertTrue(CatalogRegionBuckets::isSpbCanonical('Санкт-Петербург'));
        $this->assertTrue(CatalogRegionBuckets::isSpbCanonical('Ленинградская область'));
        $this->assertFalse(CatalogRegionBuckets::isMoscowCanonical('Казань'));
        $this->assertFalse(CatalogRegionBuckets::isSpbCanonical('Казань'));
    }

    public function test_channel_title_needles_cover_common_markers(): void
    {
        $moscow = CatalogRegionBuckets::moscowChannelTitleNeedles();
        $this->assertContains('москва', $moscow);
        $this->assertContains('подмосков', $moscow);
        $this->assertContains('мск', $moscow);

        $spb = CatalogRegionBuckets::spbChannelTitleNeedles();
        $this->assertContains('санкт-петербург', $spb);
        $this->assertContains('ленинград', $spb);
        $this->assertContains('спб', $spb);
    }
}
