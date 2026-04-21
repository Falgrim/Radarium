<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BuilderAiPromptInjector;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class BuilderAiPromptInjectorTest extends TestCase
{
    public function test_replaces_placeholder(): void
    {
        $rows = collect([
            (object) ['title' => 'Б'],
            (object) ['title' => 'А'],
        ]);

        $out = BuilderAiPromptInjector::injectSpecialitiesList("Intro\n".BuilderAiPromptInjector::PLACEHOLDER."\nEnd", $rows);

        $this->assertStringContainsString('- А', $out);
        $this->assertStringContainsString('- Б', $out);
        $this->assertStringNotContainsString(BuilderAiPromptInjector::PLACEHOLDER, $out);
    }

    public function test_appends_when_placeholder_missing(): void
    {
        $rows = collect([(object) ['title' => 'Тест']]);

        $out = BuilderAiPromptInjector::injectSpecialitiesList('Только текст без плейсхолдера.', $rows);

        $this->assertStringContainsString('- Тест', $out);
        $this->assertStringContainsString('Только текст', $out);
    }
}
