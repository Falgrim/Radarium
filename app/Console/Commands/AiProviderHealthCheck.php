<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Models\ApiAi;
use App\Services\AiProviderHealthAlertService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AiProviderHealthCheck extends Command
{
    protected $signature = 'app:ai:health-check';

    protected $description = 'Проверка доступности AI-провайдеров (Ollama) и алерт при недоступности';

    public function handle(AiProviderHealthAlertService $alerts): int
    {
        $endpoints = $this->collectOllamaEndpoints();

        if ($endpoints === []) {
            $this->info('Нет активных Ollama-провайдеров для проверки.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($endpoints as $item) {
            $label = $item['label'];
            $host = $item['host'];
            $url = $host.'/api/tags';

            try {
                $response = Http::timeout((int) config('services.ai.health_check_timeout', 10))->get($url);

                if ($response->successful()) {
                    $alerts->notifyRecovered($label, $host);
                    $this->info("OK: {$label} ({$host})");

                    continue;
                }

                $failures++;
                $detail = 'HTTP '.$response->status().': '.$response->body();
                $alerts->notifyUnavailable($label, $host, $detail);
                $this->error("FAIL: {$label} ({$host}) — {$detail}");
            } catch (ConnectionException $e) {
                $failures++;
                $alerts->notifyUnavailable($label, $host, $e->getMessage());
                $this->error("FAIL: {$label} ({$host}) — ".$e->getMessage());
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{label: string, host: string}>
     */
    private function collectOllamaEndpoints(): array
    {
        $items = [];

        $activeAis = ApiAi::query()
            ->where('status', ApiAiStatusEnum::Active)
            ->where('api_source', ApiAiSourceEnum::OllamaQwen)
            ->get(['id', 'title', 'options']);

        foreach ($activeAis as $ai) {
            $options = is_array($ai->options) ? $ai->options : [];
            $host = rtrim((string) ($options['host'] ?? config('services.ollama.host', '')), '/');
            if ($host === '') {
                continue;
            }

            $items[$host] = [
                'label' => trim((string) $ai->title) !== '' ? (string) $ai->title : 'Ollama Qwen',
                'host' => $host,
            ];
        }

        return array_values($items);
    }
}
