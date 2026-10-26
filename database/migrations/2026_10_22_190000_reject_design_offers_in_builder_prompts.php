<?php

declare(strict_types=1);

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Services\BuilderOllamaParsePrompt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLLAMA_MARKER = 'Проект и картинка без работ на объекте';

    private const PASS1_OLD = '4) Проектная/BIM-реклама, визуализация, веб/UI-дизайн, чертёж Revit, рабочая документация дизайн-проектов (ППР, АР, КР, ГСН, «разработаем комплект», «проектирую», #визуализатор, behance, 3ds max) без полевых работ как услуги бригады → мусор.';

    private const PASS2_OLD = '- Спам без смысла, почти без кириллицы, проектные разделы (ППР, АР, BIM и т.п.) без полевых работ как услуги бригады — "type":"мусор".';

    public function up(): void
    {
        $now = now();
        $prompt = BuilderOllamaParsePrompt::TEXT;

        $channelIds = DB::table('api_channels as c')
            ->join('api_ais as a', 'a.id', '=', 'c.api_ai_id')
            ->where('c.is_company', ApiDataTypeEnum::Builder->value)
            ->where('a.api_source', ApiAiSourceEnum::OllamaQwen->value)
            ->where('c.ai_promt', 'like', '%'.self::OLLAMA_MARKER.'%')
            ->pluck('c.id');

        if ($channelIds->isNotEmpty()) {
            DB::table('api_channels')->whereIn('id', $channelIds)->update([
                'ai_promt' => $prompt,
                'updated_at' => $now,
            ]);
        }

        if (DB::getSchemaBuilder()->hasTable('admin_system_prompt_presets')) {
            DB::table('admin_system_prompt_presets')
                ->where('scope', 'builder')
                ->where('api_source', ApiAiSourceEnum::OllamaQwen->value)
                ->where('body', 'like', '%'.self::OLLAMA_MARKER.'%')
                ->update([
                    'body' => $prompt,
                    'updated_at' => $now,
                ]);
        }

        $this->replaceConfigurationFragment(
            'builder_pass1_prompt',
            self::PASS1_OLD,
            $this->pass1Rule(),
            $now,
        );
        $this->replaceConfigurationFragment(
            'builder_pass2_default_prompt',
            self::PASS2_OLD,
            $this->pass2Rule(),
            $now,
        );
    }

    public function down(): void
    {
        // Тексты промптов заменяются целиком или фрагментом и прежняя редакция не хранится.
    }

    private function replaceConfigurationFragment(string $name, string $old, string $new, mixed $now): void
    {
        if (! DB::getSchemaBuilder()->hasTable('configurations')) {
            return;
        }

        $row = DB::table('configurations')->where('name', $name)->first();
        if ($row === null || ! is_string($row->value) || ! str_contains($row->value, $old)) {
            return;
        }

        DB::table('configurations')->where('name', $name)->update([
            'value' => str_replace($old, $new, $row->value),
            'updated_at' => $now,
        ]);
    }

    private function pass1Rule(): string
    {
        return '4) Автор предлагает проектирование, документацию, чертёж или 3D-модель (проектировщик, архитектор, стадия ПД, стадия РД, AutoCAD, Revit, BIM-модель, 3D-моделирование, визуализация, экспертиза проекта, МГЭ, 3ds Max, веб/UI-дизайн), а не монтаж и ремонт → мусор. «Предлагаем услуги», здания и электроснабжение это не отменяют. Монтаж или ремонт по готовому чертежу, ППР, ПТО и сметы — не этот пункт.';
    }

    private function pass2Rule(): string
    {
        return <<<'TXT'
- Спам без смысла, почти без кириллицы — "type":"мусор".
- Проектирование, проектная или рабочая документация, чертёж, 3D-модель, визуализация, архитектор, стадии ПД и РД, AutoCAD, Revit, BIM — "type":"мусор", даже если автор пишет «предлагаем услуги» и называет здания или электроснабжение. Монтаж и ремонт по готовому чертежу, ППР, ПТО и сметы — не этот случай.
TXT;
    }
};
