<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Builder;
use App\Services\RussianRegionNormalizer;
use App\Support\CatalogBuilderRegionDisplay;
use PHPUnit\Framework\TestCase;

class CatalogBuilderRegionDisplayTest extends TestCase
{
    private function normalizer(): RussianRegionNormalizer
    {
        return RussianRegionNormalizer::forTesting(
            [
                'federal_subjects' => [
                    'Москва',
                    'Московская область',
                    'Санкт-Петербург',
                    'Ленинградская область',
                    'Республика Татарстан',
                ],
                'aliases' => [
                    'балашиха' => 'Москва',
                    'химки' => 'Москва',
                    'мо' => 'Московская область',
                    'гатчина' => 'Санкт-Петербург',
                    'мурино' => 'Санкт-Петербург',
                    'ло' => 'Ленинградская область',
                ],
            ],
            ['Казань', 'Краснодар']
        );
    }

    public function test_balashikha_becomes_moscow_agglomeration(): void
    {
        $n = $this->normalizer();
        $this->assertSame(
            CatalogBuilderRegionDisplay::MOSCOW_AGGLOMERATION,
            CatalogBuilderRegionDisplay::toDisplayLabel('Балашиха', $n, keepRawIfUnknown: true)
        );
    }

    public function test_gatchina_becomes_spb_agglomeration(): void
    {
        $n = $this->normalizer();
        $this->assertSame(
            CatalogBuilderRegionDisplay::SPB_AGGLOMERATION,
            CatalogBuilderRegionDisplay::toDisplayLabel('Гатчина', $n, keepRawIfUnknown: true)
        );
    }

    public function test_stored_moscow_and_mo_map_to_agglomeration_label(): void
    {
        $n = $this->normalizer();
        $this->assertSame(
            CatalogBuilderRegionDisplay::MOSCOW_AGGLOMERATION,
            CatalogBuilderRegionDisplay::toDisplayLabel('Москва', $n)
        );
        $this->assertSame(
            CatalogBuilderRegionDisplay::MOSCOW_AGGLOMERATION,
            CatalogBuilderRegionDisplay::toDisplayLabel('Московская область', $n)
        );
    }

    public function test_other_city_kept_as_is(): void
    {
        $n = $this->normalizer();
        $this->assertSame('Казань', CatalogBuilderRegionDisplay::toDisplayLabel('Казань', $n));
    }

    public function test_unknown_settlement_kept_from_message(): void
    {
        $n = $this->normalizer();
        $this->assertSame(
            'посёлок Лесной',
            CatalogBuilderRegionDisplay::toDisplayLabel('посёлок Лесной', $n, keepRawIfUnknown: true)
        );
    }

    public function test_builder_falls_back_to_location_city_then_missing(): void
    {
        $n = $this->normalizer();

        $withCity = new Builder([
            'region' => null,
            'location_city' => 'Балашиха',
            'location_region' => null,
        ]);
        $this->assertSame(
            CatalogBuilderRegionDisplay::MOSCOW_AGGLOMERATION,
            CatalogBuilderRegionDisplay::labelForBuilder($withCity, $n)
        );

        $unknown = new Builder([
            'region' => null,
            'location_city' => 'деревня Ивановка',
            'location_region' => null,
        ]);
        $this->assertSame(
            'деревня Ивановка',
            CatalogBuilderRegionDisplay::labelForBuilder($unknown, $n)
        );

        $empty = new Builder([
            'region' => null,
            'location_city' => null,
            'location_region' => null,
        ]);
        $this->assertSame(
            CatalogBuilderRegionDisplay::MISSING,
            CatalogBuilderRegionDisplay::labelForBuilder($empty, $n)
        );
    }
}
