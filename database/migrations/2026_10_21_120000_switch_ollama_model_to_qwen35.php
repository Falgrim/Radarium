<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NEW_MODEL = 'qwen3.5:9b';

    private const OLD_MODEL = 'qwen2.5:7b-instruct-q4_K_M';

    public function up(): void
    {
        $rows = DB::table('api_ais')->where('api_source', 'ollama_qwen')->get();

        foreach ($rows as $row) {
            $options = $this->options($row->options);
            $options['model'] = self::NEW_MODEL;

            DB::table('api_ais')->where('id', $row->id)->update([
                'title' => $this->replaceLegacyName($row->title),
                'description' => $this->replaceLegacyName($row->description),
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('api_ais')->where('api_source', 'ollama_qwen')->get();

        foreach ($rows as $row) {
            $options = $this->options($row->options);
            if (($options['model'] ?? null) === self::NEW_MODEL) {
                $options['model'] = self::OLD_MODEL;
            }

            DB::table('api_ais')->where('id', $row->id)->update([
                'title' => $this->restoreLegacyName($row->title),
                'description' => $this->restoreLegacyName($row->description),
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function options(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function replaceLegacyName(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $updated = preg_replace('/qwen2\.5(?::[^\s,"\']*)?/i', self::NEW_MODEL, $value) ?? $value;

        return str_ireplace(['Qwen2.5', 'Qwen 2.5'], 'Qwen 3.5', $updated);
    }

    private function restoreLegacyName(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $updated = str_replace(self::NEW_MODEL, self::OLD_MODEL, $value);

        return str_replace('Qwen 3.5', 'Qwen 2.5', $updated);
    }
};
