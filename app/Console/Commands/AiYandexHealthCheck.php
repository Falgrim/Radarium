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

class AiYandexHealthCheck extends Command
{
    private const ENDPOINT_LABEL = 'yandex.cloud';

    private const COMPLETION_URL = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

    protected $signature = 'app:ai:yandex-health-check';

    protected $description = 'Проверка доступности YandexGPT и алерт при недоступности';

    public function handle(AiProviderHealthAlertService $alerts): int
    {
        $endpoints = $this->collectYandexEndpoints();

        if ($endpoints === []) {
            $this->info('Нет активных YandexGPT-провайдеров для проверки.');

            return self::SUCCESS;
        }

        $failures = 0;
        // rescue: в юнит-тестах без БД подсчёт очереди недоступен — алерт не должен падать из-за этого
        $postsInQueue = (int) rescue(fn () => $alerts->countBuilderPostsInQueue(), 0, false);

        foreach ($endpoints as $item) {
            $label = $item['label'];
            $apiKey = $item['api_key'];
            $folderId = $item['folder_id'];

            if ($apiKey === '' || $folderId === '') {
                $failures++;
                $detail = 'Не заданы API_KEY_TOKEN и/или Folder_id в options провайдера';
                $alerts->notifyUnavailable($label, self::ENDPOINT_LABEL, $detail, $postsInQueue);
                $this->error("FAIL: {$label} (".self::ENDPOINT_LABEL.") — {$detail}");

                continue;
            }

            try {
                $response = Http::timeout((int) config('services.ai.health_check_timeout', 10))
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Api-Key '.$apiKey,
                    ])
                    ->post(self::COMPLETION_URL, [
                        'modelUri' => 'gpt://'.$folderId.'/yandexgpt/rc',
                        'completionOptions' => [
                            'stream' => false,
                            'temperature' => 0.0,
                            'maxTokens' => 5,
                        ],
                        'messages' => [
                            [
                                'role' => 'system',
                                'text' => 'Ответь одним словом: ok',
                            ],
                            [
                                'role' => 'user',
                                'text' => 'ping',
                            ],
                        ],
                    ]);

                if ($response->successful() && is_array($response->json('result.alternatives'))) {
                    $alerts->notifyRecovered($label, self::ENDPOINT_LABEL);
                    $this->info("OK: {$label} (".self::ENDPOINT_LABEL.")");

                    continue;
                }

                $failures++;
                $detail = 'HTTP '.$response->status().': '.$response->body();
                $alerts->notifyUnavailable($label, self::ENDPOINT_LABEL, $detail, $postsInQueue);
                $this->error("FAIL: {$label} (".self::ENDPOINT_LABEL.") — {$detail}");
            } catch (ConnectionException $e) {
                $failures++;
                $alerts->notifyUnavailable($label, self::ENDPOINT_LABEL, $e->getMessage(), $postsInQueue);
                $this->error("FAIL: {$label} (".self::ENDPOINT_LABEL.") — ".$e->getMessage());
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{label: string, api_key: string, folder_id: string}>
     */
    protected function collectYandexEndpoints(): array
    {
        $items = [];

        $activeAis = ApiAi::query()
            ->where('status', ApiAiStatusEnum::Active)
            ->where('api_source', ApiAiSourceEnum::YandexGTP4)
            ->get(['id', 'title', 'options']);

        foreach ($activeAis as $ai) {
            $options = is_array($ai->options) ? $ai->options : [];
            $apiKey = trim((string) ($options['API_KEY_TOKEN'] ?? ''));
            $folderId = trim((string) ($options['Folder_id'] ?? ''));
            $dedupeKey = hash('sha256', mb_strtolower($apiKey.'|'.$folderId));

            $items[$dedupeKey] = [
                'label' => trim((string) $ai->title) !== '' ? (string) $ai->title : 'Yandex GPT',
                'api_key' => $apiKey,
                'folder_id' => $folderId,
            ];
        }

        return array_values($items);
    }
}
