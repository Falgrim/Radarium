<?php

declare(strict_types=1);

use App\Enum\ApiDataTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MARKER = 'BUILDER_GIG_CLASSIFICATION_BLOCK_V20260430';

    private function snippet(): string
    {
        return <<<'TXT'


[ДОПОЛНЕНИЕ КЛАССИФИКАЦИИ ТИПА] BUILDER_GIG_CLASSIFICATION_BLOCK_V20260430
Подработка/смена найма = «вакансия», даже если нет слов «вакансия»/«ищем»: сбор у метро или маршрута автобуса + конкретный выход ко времени; сумма за указанное число часов смены (например «3300₽ (8 часов)» и интервал «с 8:00–16:00»); «оплата по окончании/после работы на карту/перевод»; просьба прислать в ЛС возраст, ФИО, телефон; формулировки «кто готов», «кто может», «откликнитесь» в связке со сменой и оплатой найму; от заказчика «перекус дадут», «без обеда» при описании смены — это набор исполнителей, не предложение услуги работника.
Если в тексте есть несколько таких маркеров — всегда «вакансия», а не «предложение услуги».
TXT;
    }

    public function up(): void
    {
        $snippet = $this->snippet();
        $needle = self::MARKER;

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
        // Не откатываем: нельзя безопасно вырезать блок из кастомных правок.
    }
};
