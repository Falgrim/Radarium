<?php

declare(strict_types=1);

use App\Enum\ApiDataTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MARKER = 'BUILDER_BINARY_RISK_CLARIFICATIONS_V20260504';

    private function snippet(): string
    {
        return <<<'TXT'


[УТОЧНЕНИЯ КЛАССИФИКАЦИИ] BUILDER_BINARY_RISK_CLARIFICATIONS_V20260504
Заказчик описывает, что нужно сделать, без самопрезентации исполнителя («нужно смонтировать», «надо сделать», короткий запрос + «в лс» без «мы делаем/выполняю») → type: «мусор».
Вопрос к аудитории «кто свободен?», «есть кто на объект?» без описания своей бригады как исполнителя → «мусор». Не путать с «свободен/свободна бригада» в смысле готовности к заказам → «предложение услуги».
«Ищу заказ / объект» у исполнителя — «предложение услуги»; «ищем людей/мастер/бригаду» — набор персонала → «мусор».
«Требуются / нужны» + число людей или «бригада 10-15 человек» + оплата/объём как найм → «мусор».
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
