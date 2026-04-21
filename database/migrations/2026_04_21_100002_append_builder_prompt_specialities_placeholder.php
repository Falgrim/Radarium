<?php

declare(strict_types=1);

use App\Enum\ApiDataTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function appendSnippet(): string
    {
        return <<<'TXT'


РАЗРЕШЁННЫЕ ЗНАЧЕНИЯ ПОЛЯ specialities (строго из списка ниже; при обработке плейсхолдер заменяется на актуальный каталог):
{{SPECIALITIES_LIST}}

Правила specialities:
- В specialities указывай только title-строки из списка выше (после подстановки — дословно, как в каталоге).
- Если подходит несколько видов работ — перечисли все (массив), максимум 5.
- Если ни одна строка из списка не подходит — пустой массив [].
TXT;
    }

    public function up(): void
    {
        $snippet = $this->appendSnippet();
        $needle = '{{SPECIALITIES_LIST}}';

        $channels = DB::table('api_channels')
            ->where('is_company', ApiDataTypeEnum::Builder->value)
            ->select(['id', 'ai_promt'])
            ->get();

        foreach ($channels as $row) {
            $body = (string) ($row->ai_promt ?? '');
            if (str_contains($body, $needle)) {
                continue;
            }
            DB::table('api_channels')->where('id', $row->id)->update([
                'ai_promt' => $body.$snippet,
                'updated_at' => now(),
            ]);
        }

        if (! DB::getSchemaBuilder()->hasTable('admin_system_prompt_presets')) {
            return;
        }

        $presets = DB::table('admin_system_prompt_presets')
            ->where('scope', 'builder')
            ->select(['id', 'body'])
            ->get();

        foreach ($presets as $row) {
            $body = (string) ($row->body ?? '');
            if (str_contains($body, $needle)) {
                continue;
            }
            DB::table('admin_system_prompt_presets')->where('id', $row->id)->update([
                'body' => $body.$snippet,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Не откатываем: нельзя безопасно вырезать добавленный блок из кастомных правок.
    }
};
