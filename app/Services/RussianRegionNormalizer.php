<?php

namespace App\Services;

/**
 * Нормализация строки региона к каноническому названию из справочника
 * (субъекты РФ + города ≥100 тыс. + алиасы и коды ГАИ из config/russian_geography.php).
 */
class RussianRegionNormalizer
{
    /** @var list<string>|null */
    private ?array $canonicalSorted = null;

    /** @var array<string, string>|null normalized_key => canonical */
    private ?array $lookup = null;

    /**
     * @param  array<string, mixed>|null  $geographyOverride  Для тестов: тот же формат, что config('russian_geography').
     */
    public function __construct(
        private readonly string $citiesJsonPath,
        private readonly ?array $geographyOverride = null
    ) {}

    public static function withDefaultPaths(): self
    {
        return new self(base_path('app/Data/russian_cities_100k.json'));
    }

    /**
     * @param  array<string, mixed>  $geography  ['federal_subjects' => string[], 'aliases' => array<string,string>]
     * @param  list<string>  $cities
     */
    public static function forTesting(array $geography, array $cities): self
    {
        $path = sys_get_temp_dir().'/radarium_russian_cities_test_'.uniqid('', true).'.json';
        file_put_contents($path, json_encode($cities, JSON_UNESCAPED_UNICODE));

        return new self($path, $geography);
    }

    /**
     * @return array<string, mixed>
     */
    private function geography(): array
    {
        return $this->geographyOverride ?? config('russian_geography', []);
    }

    /**
     * @return list<string>
     */
    public function canonicalSorted(): array
    {
        if ($this->canonicalSorted !== null) {
            return $this->canonicalSorted;
        }

        $geo = $this->geography();
        $subjects = $geo['federal_subjects'] ?? [];
        $cities = is_readable($this->citiesJsonPath)
            ? json_decode(file_get_contents($this->citiesJsonPath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $merged = array_values(array_unique(array_merge($subjects, is_array($cities) ? $cities : [])));
        sort($merged, SORT_STRING);
        $this->canonicalSorted = $merged;

        return $this->canonicalSorted;
    }

    /**
     * Для <select>: value и подпись — каноническое имя.
     *
     * @return array<string, string>
     */
    public function selectOptions(): array
    {
        $opts = [];
        foreach ($this->canonicalSorted() as $name) {
            $opts[$name] = $name;
        }

        return $opts;
    }

    /**
     * Привести сырую строку (ИИ, ручной ввод) к каноническому названию из справочника.
     * Если сопоставления нет — возвращает null (поле region лучше оставить пустым, чем «мусор»).
     */
    public function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $this->buildLookup();

        $key = $this->normalizeKey($trimmed);
        if (isset($this->lookup[$key])) {
            return $this->lookup[$key];
        }

        foreach ($this->splitSegments($trimmed) as $segment) {
            $sk = $this->normalizeKey($segment);
            if (isset($this->lookup[$sk])) {
                return $this->lookup[$sk];
            }
        }

        $longest = $this->matchLongestCanonicalSubstring($trimmed);
        if ($longest !== null) {
            return $longest;
        }

        return null;
    }

    /**
     * Как {@see normalize()}, но если не найдено — вернуть исходную обрезанную строку.
     */
    public function normalizeOrKeep(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return $this->normalize($raw) ?? trim($raw);
    }

    private function buildLookup(): void
    {
        if ($this->lookup !== null) {
            return;
        }

        $this->lookup = [];
        foreach ($this->canonicalSorted() as $canonical) {
            $this->lookup[$this->normalizeKey($canonical)] = $canonical;
        }

        $aliases = $this->geography()['aliases'] ?? [];
        foreach ($aliases as $alias => $canonical) {
            if (! is_string($canonical)) {
                continue;
            }
            $aliasStr = is_string($alias) || is_int($alias) ? (string) $alias : '';
            if ($aliasStr === '') {
                continue;
            }
            $this->lookup[$this->normalizeKey($aliasStr)] = $canonical;
        }
    }

    private function normalizeKey(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(['ё'], ['е'], $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);
        $s = preg_replace('/^г\.?\s+/u', '', $s) ?? $s;
        $s = preg_replace('/^г\s+/u', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * @return list<string>
     */
    private function splitSegments(string $s): array
    {
        $parts = preg_split('#[,;/|]+#u', $s) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    private function matchLongestCanonicalSubstring(string $raw): ?string
    {
        $hay = $this->normalizeKey($raw);
        if ($hay === '') {
            return null;
        }

        $candidates = $this->canonicalSorted();
        usort($candidates, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($candidates as $canonical) {
            $needle = $this->normalizeKey($canonical);
            if ($needle === '') {
                continue;
            }
            if (mb_strpos($hay, $needle, 0, 'UTF-8') !== false) {
                return $canonical;
            }
        }

        return null;
    }
}
