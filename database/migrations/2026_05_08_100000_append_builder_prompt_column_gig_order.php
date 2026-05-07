<?php

declare(strict_types=1);

use App\Enum\ApiDataTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MARKER = 'BUILDER_ORDER_COLUMNS_TOURS_V20260508';

    private function snippet(): string
    {
        return <<<'TXT'


[ДОПОЛНЕНИЕ КЛАССИФИКАЦИИ] BUILDER_ORDER_COLUMNS_TOURS_V20260508
Типичное ТЗ заказчика (не «резюме» и не «предложение услуги» заказчиком через карточку исполнителя): шапка «Город, населённый пункт/район»; объём через штуки и габариты («есть N колонн», «по X-Y квадратов каждая», «высота … метра»); «туры есть» как инвентарь лесов; ставка за площадь («цена … р/м²») без признаков бригады-исполнителя («выполняем», «ищем заказ», «бригада», «выезжаем»). Если сочетается ставка за м² с указанием заданного набора объектов объёмом («колонны», несколько точных квадратов на объект) без самопрезентации исполнителя — классифицируй как «вакансия», а не услугу.
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
