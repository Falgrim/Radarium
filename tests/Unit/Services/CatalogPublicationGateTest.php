<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Services\CatalogPublicationGate;
use Tests\TestCase;

final class CatalogPublicationGateTest extends TestCase
{
    public function test_when_disabled_always_active(): void
    {
        config(['catalog_publication_gate.enabled' => false]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Builder, 'текст', []);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_when_enabled_and_no_speciality_is_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Builder, 'текст', []);

        $this->assertSame(ApiPostAiStatusEnum::InModeration, $out['status']);
        $this->assertContains('no_dictionary_speciality_matched', $out['reasons']);
    }

    public function test_when_enabled_and_has_speciality_is_active(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
        ]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Specialist, 'текст', [101]);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_require_speciality_off_allows_empty_matches(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => false,
        ]);

        $gate = new CatalogPublicationGate;
        $out = $gate->decide(ApiDataTypeEnum::Builder, 'текст', []);

        $this->assertSame(ApiPostAiStatusEnum::Active, $out['status']);
    }

    public function test_logs_to_catalog_publication_gate_file_when_moderation(): void
    {
        config([
            'catalog_publication_gate.enabled' => true,
            'catalog_publication_gate.require_matched_speciality' => true,
        ]);

        $token = 'gate_test_token_'.uniqid('', true);

        $gate = new CatalogPublicationGate;
        $gate->decide(
            ApiDataTypeEnum::Builder,
            'текст поста '.$token,
            [],
            ['test_token' => $token],
        );

        $path = storage_path('logs/catalog_publication_gate-'.date('Y-m-d').'.log');
        $this->assertFileExists($path);
        $this->assertStringContainsString($token, (string) file_get_contents($path));
    }
}
