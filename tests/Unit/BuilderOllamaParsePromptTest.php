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
        $this->assertStringNotContainsString('Проект и картинка без работ на объекте', $prompt);
        $this->assertStringContainsString('3D-модель', $prompt);
        $this->assertStringContainsString('стадия ПД', $prompt);
        $this->assertStringContainsString('стадия РД', $prompt);
        $this->assertStringContainsString('Я архитектор, работаю в Revit, предлагаю услуги', $prompt);
        $this->assertStringContainsString('Электромонтаж по чертежам заказчика, бригада выезжает', $prompt);

        $designRule = mb_strpos($prompt, 'Автор предлагает сделать проект');
        $serviceRule = mb_strpos($prompt, '3) "предложение услуги"');
        $this->assertNotFalse($designRule);
        $this->assertNotFalse($serviceRule);
        $this->assertLessThan($serviceRule, $designRule);

        $schemaPos = mb_strpos($prompt, 'Схема. Все ключи обязательны.');
        $listPos = mb_strpos($prompt, BuilderAiPromptInjector::PLACEHOLDER);
        $this->assertNotFalse($schemaPos);
        $this->assertNotFalse($listPos);
        $this->assertGreaterThan($listPos, $schemaPos);
    }

    public function test_pass1_and_fallback_prompts_reject_design_and_3d_offers(): void
    {
        $pass1 = (string) config('builder_ai_pipeline.pass1_prompt');
        $pass2 = (string) config('builder_ai_pipeline.pass2_default_prompt');

        foreach ([$pass1, $pass2] as $prompt) {
            $this->assertStringContainsString('3D-модель', $prompt);
            $this->assertStringContainsString('ПД', $prompt);
            $this->assertStringContainsString('РД', $prompt);
            $this->assertStringNotContainsString('без полевых работ', $prompt);
        }
    }
}
