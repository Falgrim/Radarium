<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

final class BuilderAiPromptInjector
{
    public const PLACEHOLDER = '{{SPECIALITIES_LIST}}';

    /**
     * Подставляет в системный промпт список title специализаций builders (для закрытого списка LLM).
     *
     * @param  Collection<int, \App\Models\DictionarySpeciality>  $specialityRows
     */
    public static function injectSpecialitiesList(string $prompt, Collection $specialityRows): string
    {
        $lines = $specialityRows->sortBy(static fn ($r) => mb_strtolower((string) $r->title))
            ->map(static fn ($r) => '- '.(string) $r->title)
            ->implode("\n");

        if (str_contains($prompt, self::PLACEHOLDER)) {
            return str_replace(self::PLACEHOLDER, $lines, $prompt);
        }

        return rtrim($prompt)."\n\nРАЗРЕШЁННЫЕ ЗНАЧЕНИЯ ПОЛЯ specialities (строго из списка ниже; только эти строки в массиве specialities):\n"
            .$lines."\n";
    }
}
