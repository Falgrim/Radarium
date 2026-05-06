<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $baseOrder = (int) DB::table('configurations')->max('order');

        $rows = [
            [
                'title' => 'Builder/Ollama: двухэтапный pipeline включён',
                'name' => 'builder_two_pass_ollama_enabled',
                'type' => 'checkbox',
                'value' => '1',
                'order' => $baseOrder + 1,
                'options' => '',
                'title_hint' => 'Быстрый rollback без отката кода: выключите, чтобы вернуть старую one-pass схему для ollama_qwen.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Builder Pass 1: prompt классификатора',
                'name' => 'builder_pass1_prompt',
                'type' => 'textarea',
                'value' => (string) config('builder_ai_pipeline.pass1_prompt', ''),
                'order' => $baseOrder + 2,
                'options' => '',
                'title_hint' => 'Используется только для Pass 1 бинарной классификации (service/junk). Меняйте аккуратно: формат ответа строго {"type":"..."}',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Builder Pass 2: default prompt',
                'name' => 'builder_pass2_default_prompt',
                'type' => 'textarea',
                'value' => (string) config('builder_ai_pipeline.pass2_default_prompt', ''),
                'order' => $baseOrder + 3,
                'options' => '',
                'title_hint' => 'Fallback-промпт extraction/normalization для каналов, где ai_promt пустой.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('configurations')->updateOrInsert(
                ['name' => $row['name']],
                $row
            );
        }
    }

    public function down(): void
    {
        DB::table('configurations')
            ->whereIn('name', [
                'builder_two_pass_ollama_enabled',
                'builder_pass1_prompt',
                'builder_pass2_default_prompt',
            ])
            ->delete();
    }
};
