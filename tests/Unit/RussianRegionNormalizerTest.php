<?php

namespace Tests\Unit;

use App\Services\RussianRegionNormalizer;
use App\Support\CatalogRegionOptions;
use PHPUnit\Framework\TestCase;

class RussianRegionNormalizerTest extends TestCase
{
    private function sampleNormalizer(): RussianRegionNormalizer
    {
        return RussianRegionNormalizer::forTesting(
            [
                'federal_subjects' => [
                    'Москва',
                    'Ростовская область',
                ],
                'aliases' => [
                    'мск' => 'Москва',
                    '77' => 'Москва',
                ],
            ],
            ['Краснодар', 'Ростов-на-Дону']
        );
    }

    public function test_alias_msk_to_moscow(): void
    {
        $n = $this->sampleNormalizer();
        $this->assertSame('Москва', $n->normalize('МСК'));
        $this->assertSame('Москва', $n->normalize('г. Москва'));
    }

    public function test_vehicle_code_77(): void
    {
        $n = $this->sampleNormalizer();
        $this->assertSame('Москва', $n->normalize('77'));
    }

    public function test_exact_city_in_dictionary(): void
    {
        $n = $this->sampleNormalizer();
        $this->assertSame('Краснодар', $n->normalize('краснодар'));
    }

    public function test_substring_contains_region(): void
    {
        $n = $this->sampleNormalizer();
        $this->assertSame('Ростовская область', $n->normalize('Ростовская область, выезд 100 км'));
    }

    public function test_normalize_or_keep_unknown(): void
    {
        $n = $this->sampleNormalizer();
        $this->assertSame('Деревня Неизвестная 123', $n->normalizeOrKeep('Деревня Неизвестная 123'));
    }

    public function test_suburb_alias_khimki_to_moscow(): void
    {
        $n = RussianRegionNormalizer::forTesting(
            [
                'federal_subjects' => ['Москва'],
                'aliases' => ['химки' => 'Москва'],
            ],
            []
        );
        $this->assertSame('Москва', $n->normalize('Химки'));
    }

    public function test_catalog_canonical_dropdown_merges_synonyms(): void
    {
        $normalizer = RussianRegionNormalizer::forTesting(
            [
                'federal_subjects' => ['Москва', 'Московская область'],
                'aliases' => [
                    'мо' => 'Московская область',
                    'сао' => 'Москва',
                ],
            ],
            []
        );
        $whitelist = [
            'Москва' => 'Москва',
            'Московская область' => 'Московская область',
        ];
        $out = CatalogRegionOptions::catalogCanonicalChoicesFromDistinctRaw(
            $normalizer,
            ['МО', 'Московская область', 'САО', 'null'],
            $whitelist
        );
        $this->assertSame([
            'Москва' => 'Москва',
            'Московская область' => 'Московская область',
        ], $out);
    }
}
