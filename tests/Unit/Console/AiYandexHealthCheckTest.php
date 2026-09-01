<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\AiYandexHealthCheck;
use App\Services\AiProviderHealthAlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class AiYandexHealthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.ai.alert_enabled' => true,
            'services.ai.alert_throttle_minutes' => 60,
            'services.ai.health_check_timeout' => 5,
        ]);
    }

    public function test_no_active_yandex_providers_exits_ok(): void
    {
        [$code, $output] = $this->runCommand($this->commandWithEndpoints([]));

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Нет активных YandexGPT', $output);
    }

    public function test_successful_completion_reports_ok_and_clears_down_state(): void
    {
        $endpoint = 'yandex.cloud';
        Cache::put(
            'ai_provider_was_down:'.hash('sha256', mb_strtolower($endpoint)),
            true,
            now()->addDays(7)
        );
        Cache::put('ai_provider_health_active_banners', [
            hash('sha256', mb_strtolower($endpoint)) => [
                'provider_label' => 'Yandex prod',
                'endpoint' => $endpoint,
                'detail' => 'was down',
                'posts_in_queue' => 0,
                'since' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ], now()->addDays(30));

        Http::fake([
            'llm.api.cloud.yandex.net/*' => Http::response([
                'result' => [
                    'alternatives' => [
                        ['message' => ['role' => 'assistant', 'text' => 'ok']],
                    ],
                ],
            ], 200),
        ]);

        [$code, $output] = $this->runCommand($this->commandWithEndpoints([
            [
                'label' => 'Yandex prod',
                'api_key' => 'test-key',
                'folder_id' => 'b1folder',
            ],
        ]));

        $this->assertSame(0, $code);
        $this->assertStringContainsString('OK: Yandex prod', $output);
        $this->assertSame([], app(AiProviderHealthAlertService::class)->getActiveBanners());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion'
                && $request->hasHeader('Authorization', 'Api-Key test-key')
                && ($request['modelUri'] ?? null) === 'gpt://b1folder/yandexgpt/rc';
        });
    }

    public function test_http_error_reports_fail_and_registers_banner(): void
    {
        Http::fake([
            'llm.api.cloud.yandex.net/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        [$code, $output] = $this->runCommand($this->commandWithEndpoints([
            [
                'label' => 'Yandex down',
                'api_key' => 'test-key',
                'folder_id' => 'b1folder',
            ],
        ]));

        $this->assertSame(1, $code);
        $this->assertStringContainsString('FAIL: Yandex down', $output);

        $banners = app(AiProviderHealthAlertService::class)->getActiveBanners();
        $this->assertCount(1, $banners);
        $this->assertSame('Yandex down', $banners[0]['provider_label']);
        $this->assertSame('yandex.cloud', $banners[0]['endpoint']);
        $this->assertStringContainsString('HTTP 503', $banners[0]['detail']);
    }

    public function test_missing_credentials_reports_fail_without_http(): void
    {
        Http::fake();

        [$code, $output] = $this->runCommand($this->commandWithEndpoints([
            [
                'label' => 'Yandex broken config',
                'api_key' => '',
                'folder_id' => '',
            ],
        ]));

        $this->assertSame(1, $code);
        $this->assertStringContainsString('FAIL: Yandex broken config', $output);
        $this->assertStringContainsString('API_KEY_TOKEN', $output);
        Http::assertNothingSent();

        $banners = app(AiProviderHealthAlertService::class)->getActiveBanners();
        $this->assertCount(1, $banners);
        $this->assertSame('yandex.cloud', $banners[0]['endpoint']);
    }

    public function test_empty_result_alternatives_is_failure(): void
    {
        Http::fake([
            'llm.api.cloud.yandex.net/*' => Http::response(['result' => []], 200),
        ]);

        [$code, $output] = $this->runCommand($this->commandWithEndpoints([
            [
                'label' => 'Yandex empty',
                'api_key' => 'test-key',
                'folder_id' => 'b1folder',
            ],
        ]));

        $this->assertSame(1, $code);
        $this->assertStringContainsString('FAIL: Yandex empty', $output);
    }

    public function test_command_is_registered(): void
    {
        $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();

        $this->assertArrayHasKey('app:ai:yandex-health-check', $commands);
    }

    /**
     * @param list<array{label: string, api_key: string, folder_id: string}> $endpoints
     */
    private function commandWithEndpoints(array $endpoints): AiYandexHealthCheck
    {
        $command = new class($endpoints) extends AiYandexHealthCheck
        {
            /** @param list<array{label: string, api_key: string, folder_id: string}> $endpoints */
            public function __construct(private array $endpoints)
            {
                parent::__construct();
            }

            protected function collectYandexEndpoints(): array
            {
                return $this->endpoints;
            }
        };
        $command->setLaravel($this->app);

        return $command;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(AiYandexHealthCheck $command): array
    {
        $output = new BufferedOutput();
        $code = $command->run(new ArrayInput([]), $output);

        return [$code, $output->fetch()];
    }
}
