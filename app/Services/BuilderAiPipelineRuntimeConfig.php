<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Configuration;

final class BuilderAiPipelineRuntimeConfig
{
    /** @var array<string, string>|null */
    private ?array $settingsCache = null;

    public function twoPassOllamaEnabled(): bool
    {
        $fallback = (bool) config('builder_ai_pipeline.two_pass_ollama_enabled', true);
        $raw = $this->getSettingValue('builder_two_pass_ollama_enabled');
        if ($raw === null) {
            return $fallback;
        }

        $normalized = mb_strtolower(trim($raw));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'да'], true);
    }

    public function pass1Prompt(): string
    {
        return $this->getNonEmptySettingValue('builder_pass1_prompt')
            ?? (string) config('builder_ai_pipeline.pass1_prompt', '');
    }

    public function pass2DefaultPrompt(): string
    {
        return $this->getNonEmptySettingValue('builder_pass2_default_prompt')
            ?? (string) config('builder_ai_pipeline.pass2_default_prompt', '');
    }

    private function getNonEmptySettingValue(string $name): ?string
    {
        $raw = $this->getSettingValue($name);
        if ($raw === null) {
            return null;
        }

        $trim = trim($raw);

        return $trim === '' ? null : $trim;
    }

    private function getSettingValue(string $name): ?string
    {
        $cache = $this->settings();

        return $cache[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        $rows = Configuration::query()
            ->whereIn('name', [
                'builder_two_pass_ollama_enabled',
                'builder_pass1_prompt',
                'builder_pass2_default_prompt',
            ])
            ->get(['name', 'value']);

        $cache = [];
        foreach ($rows as $row) {
            $cache[(string) $row->name] = (string) $row->value;
        }

        $this->settingsCache = $cache;

        return $this->settingsCache;
    }
}
