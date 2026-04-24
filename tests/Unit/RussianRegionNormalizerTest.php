<?php

namespace Tests\Unit;

use App\Services\RussianRegionNormalizer;
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
}
