<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PUBLIC_HOST = '79.175.45.41';

    private const HOST_HEADER = '192.168.88.127:11434';

    public function up(): void
    {
        $envHost = (string) config('services.ollama.host', '');

        foreach (DB::table('api_ais')->where('api_source', 'ollama_qwen')->get() as $row) {
            $options = $this->options($row->options);
            $host = (string) ($options['host'] ?? $envHost);
            if (! str_contains($host, self::PUBLIC_HOST)) {
                continue;
            }
            if (trim((string) ($options['host_header'] ?? '')) === self::HOST_HEADER) {
                continue;
            }

            $options['host_header'] = self::HOST_HEADER;
            DB::table('api_ais')->where('id', $row->id)->update([
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('api_ais')->where('api_source', 'ollama_qwen')->get() as $row) {
            $options = $this->options($row->options);
            if (($options['host_header'] ?? null) !== self::HOST_HEADER) {
                continue;
            }

            unset($options['host_header']);
            DB::table('api_ais')->where('id', $row->id)->update([
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
};
