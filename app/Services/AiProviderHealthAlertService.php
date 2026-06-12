<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MoonShine\Notifications\MoonShineNotification;
use Throwable;

final class AiProviderHealthAlertService
{
    private const CACHE_ALERT_PREFIX = 'ai_provider_down_alert:';

    private const CACHE_WAS_DOWN_PREFIX = 'ai_provider_was_down:';

    private const CACHE_ACTIVE_BANNERS = 'ai_provider_health_active_banners';

    /**
     * @return list<array{
     *     provider_label: string,
     *     endpoint: string,
     *     detail: string,
     *     posts_in_queue: int,
     *     since: string,
     *     updated_at: string
     * }>
     */
    public function getActiveBanners(): array
    {
        /** @var array<string, array<string, mixed>> $banners */
        $banners = Cache::get(self::CACHE_ACTIVE_BANNERS, []);

        return array_values($banners);
    }

    public function hasActiveBanners(): bool
    {
        return $this->getActiveBanners() !== [];
    }

    public function notifyUnavailable(
        string $providerLabel,
        string $endpoint,
        string $detail,
        ?int $postsInQueue = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            $endpoint = 'unknown';
        }

        $cacheKey = $this->alertCacheKey($endpoint);
        $wasDownKey = $this->wasDownCacheKey($endpoint);

        Cache::put($wasDownKey, true, now()->addDays(7));

        $queueHint = $postsInQueue ?? $this->countBuilderPostsInQueue();
        $this->registerActiveBanner($providerLabel, $endpoint, $detail, $queueHint);

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes($this->throttleMinutes()));

        $message = sprintf(
            '⚠️ ИИ НЕДОСТУПЕН: %s (%s). В очереди builder-постов: %d. %s',
            $providerLabel,
            $endpoint,
            $queueHint,
            $this->truncateDetail($detail)
        );

        $this->dispatchNotification($message, 'red');
        $this->logEvent('unavailable', $providerLabel, $endpoint, $detail, $queueHint);
    }

    public function notifyRecovered(string $providerLabel, string $endpoint): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            $endpoint = 'unknown';
        }

        $wasDownKey = $this->wasDownCacheKey($endpoint);
        if (! Cache::pull($wasDownKey)) {
            return;
        }

        Cache::forget($this->alertCacheKey($endpoint));
        $this->removeActiveBanner($endpoint);

        $message = sprintf('✅ ИИ снова доступен: %s (%s)', $providerLabel, $endpoint);
        $this->dispatchNotification($message, 'green');
        $this->logEvent('recovered', $providerLabel, $endpoint, null, null);
    }

    public function countBuilderPostsInQueue(): int
    {
        return (int) ApiChannelPost::query()
            ->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Builder)
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->count();
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.ai.alert_enabled', true);
    }

    private function throttleMinutes(): int
    {
        return max(5, (int) config('services.ai.alert_throttle_minutes', 60));
    }

    private function alertCacheKey(string $endpoint): string
    {
        return self::CACHE_ALERT_PREFIX.hash('sha256', mb_strtolower($endpoint));
    }

    private function wasDownCacheKey(string $endpoint): string
    {
        return self::CACHE_WAS_DOWN_PREFIX.hash('sha256', mb_strtolower($endpoint));
    }

    private function truncateDetail(string $detail, int $maxChars = 500): string
    {
        $detail = preg_replace('/\s+/u', ' ', trim($detail)) ?? '';

        if (mb_strlen($detail) <= $maxChars) {
            return $detail;
        }

        return mb_substr($detail, 0, $maxChars).'…';
    }

    private function dispatchNotification(string $message, string $color): void
    {
        try {
            MoonShineNotification::send(
                message: $message,
                button: [
                    'link' => $this->adminApiAiUrl(),
                    'label' => 'Сервисы ИИ',
                ],
                color: $color,
            );
        } catch (Throwable $e) {
            Log::warning('AiProviderHealthAlert: MoonShine-уведомление не отправлено', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function adminApiAiUrl(): string
    {
        $prefix = trim((string) config('moonshine.route.prefix', 'admin'), '/');

        return url('/'.$prefix.'/resource/api-ai-resource/api-ai-index-page');
    }

    /**
     * @param array<string, array<string, mixed>> $banners
     */
    private function registerActiveBanner(
        string $providerLabel,
        string $endpoint,
        string $detail,
        int $postsInQueue,
    ): void {
        /** @var array<string, array<string, mixed>> $banners */
        $banners = Cache::get(self::CACHE_ACTIVE_BANNERS, []);
        $key = hash('sha256', mb_strtolower($endpoint));
        $now = now()->toIso8601String();

        $banners[$key] = [
            'provider_label' => $providerLabel,
            'endpoint' => $endpoint,
            'detail' => $this->truncateDetail($detail, 220),
            'posts_in_queue' => $postsInQueue,
            'since' => isset($banners[$key]['since']) ? (string) $banners[$key]['since'] : $now,
            'updated_at' => $now,
        ];

        Cache::put(self::CACHE_ACTIVE_BANNERS, $banners, now()->addDays(30));
    }

    private function removeActiveBanner(string $endpoint): void
    {
        /** @var array<string, array<string, mixed>> $banners */
        $banners = Cache::get(self::CACHE_ACTIVE_BANNERS, []);
        unset($banners[hash('sha256', mb_strtolower($endpoint))]);

        if ($banners === []) {
            Cache::forget(self::CACHE_ACTIVE_BANNERS);

            return;
        }

        Cache::put(self::CACHE_ACTIVE_BANNERS, $banners, now()->addDays(30));
    }

    private function logEvent(
        string $event,
        string $providerLabel,
        string $endpoint,
        ?string $detail,
        ?int $postsInQueue,
    ): void {
        Log::channel('post_ai')->warning('[AiProviderHealth] '.$event, [
            'provider' => $providerLabel,
            'endpoint' => $endpoint,
            'detail' => $detail,
            'builder_posts_in_queue' => $postsInQueue,
        ]);
    }
}
