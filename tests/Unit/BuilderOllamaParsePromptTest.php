<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BuilderAiPromptInjector;
use App\Services\BuilderOllamaParsePrompt;
use Tests\TestCase;

class BuilderOllamaParsePromptTest extends TestCase
{
    public function test_prompt_matches_qwen35_extraction_contract(): void
    {
        $prompt = BuilderOllamaParsePrompt::TEXT;

        $this->assertNotSame($prompt, config('builder_ai_pipeline.pass2_default_prompt'));
        $this->assertStringContainsString(BuilderAiPromptInjector::PLACEHOLDER, $prompt);
        $this->assertStringContainsString('"type"', $prompt);
        $this->assertStringContainsString('предложение услуги', $prompt);
        $this->assertStringContainsString('вакансия', $prompt);
        $this->assertStringContainsString('мусор', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringNotContainsString('/no_think', $prompt);
        $this->assertStringNotContainsString('/nothink', $prompt);
        $this->assertStringNotContainsString('<role>', $prompt);
        $this->assertStringNotContainsString('qwen2.5', $prompt);

        $schemaPos = mb_strpos($prompt, 'Схема. Все ключи обязательны.');
        $listPos = mb_strpos($prompt, BuilderAiPromptInjector::PLACEHOLDER);
        $this->assertNotFalse($schemaPos);
        $this->assertNotFalse($listPos);
        $this->assertGreaterThan($listPos, $schemaPos);
    }
}
