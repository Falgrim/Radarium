<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiAi;
use App\Models\ApiChannel;
use Illuminate\Database\Seeder;

/**
 * Telegram-каналы строителей (тип выборки: Строитель).
 * Запуск: php artisan db:seed --class=TelegramBuilderChannelsSeeder
 *
 * Записи создаются в api_channels и отображаются в Moonshine как обычные источники.
 * Сервис ИИ: запись api_ais с title = qwen2.5:7b-instruct-q4_K_M (как в выпадающем списке Moonshine).
 * api_id / api_hash — учётные данные приложения Telegram (не меняются при переавторизации сессии).
 */
class TelegramBuilderChannelsSeeder extends Seeder
{
    private const POST_FROM_DATE = '2026-04-01';

    private const DESCRIPTION = 'Строители';

    /** Название сервиса ИИ в Moonshine (поле title в api_ais). */
    private const AI_SERVICE_TITLE = 'qwen2.5:7b-instruct-q4_K_M';

    private const TELEGRAM_API_ID = 22885091;

    private const TELEGRAM_API_HASH = '8f48f3e7ff631b446d2b101281d1a173';

    private const CHANNEL_URLS = [
        'https://t.me/stroykaspbchat',
        'https://t.me/ru_electric',
        'https://t.me/plitkatutok/872',
        'https://t.me/remontmosk/20437',
        'https://t.me/remontmosk/21547',
        'https://t.me/vsem_podryad',
        'https://t.me/subpodryaddvk',
        'https://t.me/elektrik54164',
        'https://t.me/stroiteli_m',
        'https://t.me/stroitelstvo_remont_info',
        'https://t.me/WhitemasterokMSK',
        'https://t.me/rabotmosk',
        'https://t.me/remont_NSU',
        'https://t.me/LombardMoscow77',
        'https://t.me/Avito_Remont',
    ];

    public function run(): void
    {
        $apiAi = $this->resolveQwenApiAi();
        if ($apiAi === null) {
            $this->command?->error(
                'Не найден сервис ИИ «'.self::AI_SERVICE_TITLE.'». Создайте его в Moonshine → Сервисы ИИ и повторите.'
            );

            return;
        }

        $telegramOptions = $this->telegramCredentials();

        $created = 0;
        $seenTitles = [];

        foreach (self::CHANNEL_URLS as $rawUrl) {
            $title = $this->normalizeUrl($rawUrl);
            if (isset($seenTitles[$title])) {
                continue;
            }
            $seenTitles[$title] = true;

            $parsed = $this->parseTelegramUrl($title);
            $options = $telegramOptions;
            if ($parsed['reply_to_msg_id'] !== null) {
                $options['reply_to_msg_id'] = (string) $parsed['reply_to_msg_id'];
            }

            ApiChannel::query()->updateOrCreate(
                ['title' => $title],
                [
                    'link' => $parsed['link'],
                    'description' => self::DESCRIPTION,
                    'ai_promt' => null,
                    'api_ai_id' => $apiAi->id,
                    'channel_source' => ApiChannelSourceEnum::Telegram,
                    'options' => $options,
                    'status' => ApiChannelStatusEnum::Active,
                    'is_company' => ApiDataTypeEnum::Builder,
                    'region' => $this->detectRegion($parsed['slug']),
                    'post_from_date' => self::POST_FROM_DATE,
                ]
            );

            $created++;
        }

        $this->command?->info("Telegram: создано/обновлено {$created} источников ApiChannel (сервис ИИ: {$apiAi->title}).");
    }

    private function resolveQwenApiAi(): ?ApiAi
    {
        $byTitle = ApiAi::query()
            ->where('title', self::AI_SERVICE_TITLE)
            ->first();

        if ($byTitle !== null) {
            return $byTitle;
        }

        return ApiAi::query()
            ->where('api_source', ApiAiSourceEnum::OllamaQwen)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{api_id: int, api_hash: string}
     */
    private function telegramCredentials(): array
    {
        return [
            'api_id' => self::TELEGRAM_API_ID,
            'api_hash' => self::TELEGRAM_API_HASH,
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * @return array{link: string, slug: string, reply_to_msg_id: int|null}
     */
    private function parseTelegramUrl(string $url): array
    {
        if (! preg_match('#^https://t\.me/([^/]+)(?:/(\d+))?$#i', $url, $matches)) {
            throw new \InvalidArgumentException("Некорректная ссылка Telegram: {$url}");
        }

        $slug = $matches[1];
        $topicId = isset($matches[2]) ? (int) $matches[2] : null;

        return [
            'link' => 'https://t.me/'.$slug,
            'slug' => $slug,
            'reply_to_msg_id' => $topicId,
        ];
    }

    private function detectRegion(string $slug): ?string
    {
        if (preg_match('/SPB|spb/', $slug)) {
            return 'Санкт-Петербург';
        }

        if (preg_match('/mosk|MSK|moscow|77/i', $slug)) {
            return 'Москва';
        }

        return null;
    }
}
