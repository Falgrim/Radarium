<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AiProviderHealthAlertService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AiProviderHealthAlertServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.ai.alert_enabled' => true,
            'services.ai.alert_throttle_minutes' => 60,
        ]);
    }

    public function test_unavailable_registers_persistent_banner(): void
    {
        $service = new AiProviderHealthAlertService();

        $service->notifyUnavailable('Ollama Qwen', 'http://127.0.0.1:11434', 'cURL error 7');

        $banners = $service->getActiveBanners();
        $this->assertCount(1, $banners);
        $this->assertSame('Ollama Qwen', $banners[0]['provider_label']);
        $this->assertSame('http://127.0.0.1:11434', $banners[0]['endpoint']);
    }

    public function test_recovered_removes_persistent_banner(): void
    {
        $service = new AiProviderHealthAlertService();

        $service->notifyUnavailable('Ollama Qwen', 'http://127.0.0.1:11434', 'down');
        $service->notifyRecovered('Ollama Qwen', 'http://127.0.0.1:11434');

        $this->assertSame([], $service->getActiveBanners());
    }

    public function test_unavailable_alert_is_throttled_by_endpoint(): void
    {
        $service = new AiProviderHealthAlertService();

        $service->notifyUnavailable('Ollama Qwen', 'http://127.0.0.1:11434', 'cURL error 7');
        $service->notifyUnavailable('Ollama Qwen', 'http://127.0.0.1:11434', 'cURL error 7 again');

        $cacheKey = 'ai_provider_down_alert:'.hash('sha256', mb_strtolower('http://127.0.0.1:11434'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_recovered_clears_down_state(): void
    {
        $service = new AiProviderHealthAlertService();

        $service->notifyUnavailable('Ollama Qwen', 'http://127.0.0.1:11434', 'down');
        $service->notifyRecovered('Ollama Qwen', 'http://127.0.0.1:11434');

        $wasDownKey = 'ai_provider_was_down:'.hash('sha256', mb_strtolower('http://127.0.0.1:11434'));
        $this->assertFalse(Cache::has($wasDownKey));
    }

    public function test_recovered_without_prior_down_is_noop(): void
    {
        $service = new AiProviderHealthAlertService();

        $service->notifyRecovered('Ollama Qwen', 'http://127.0.0.1:11434');

        $cacheKey = 'ai_provider_down_alert:'.hash('sha256', mb_strtolower('http://127.0.0.1:11434'));
        $this->assertFalse(Cache::has($cacheKey));
    }
}
