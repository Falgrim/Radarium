<?php

declare(strict_types=1);

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Services\BuilderOllamaParsePrompt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prompt = BuilderOllamaParsePrompt::TEXT;
        $now = now();

        $channelIds = DB::table('api_channels as c')
            ->join('api_ais as a', 'a.id', '=', 'c.api_ai_id')
            ->where('c.is_company', ApiDataTypeEnum::Builder->value)
            ->where('a.api_source', ApiAiSourceEnum::OllamaQwen->value)
            ->whereNotNull('c.ai_promt')
            ->where('c.ai_promt', '!=', '')
            ->pluck('c.id');

        if ($channelIds->isNotEmpty()) {
            DB::table('api_channels')->whereIn('id', $channelIds)->update([
                'ai_promt' => $prompt,
                'updated_at' => $now,
            ]);
        }

        if (DB::getSchemaBuilder()->hasTable('admin_system_prompt_presets')) {
            $ollamaPresets = DB::table('admin_system_prompt_presets')
                ->where('scope', 'builder')
                ->where('api_source', ApiAiSourceEnum::OllamaQwen->value);

            if ($ollamaPresets->exists()) {
                $ollamaPresets->update([
                    'body' => $prompt,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('admin_system_prompt_presets')->insert([
                    'scope' => 'builder',
                    'name' => 'Qwen 3.5',
                    'api_source' => ApiAiSourceEnum::OllamaQwen->value,
                    'body' => $prompt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Прежние тексты каналов и пресетов миграция заменяет целиком и не хранит.
    }
};
