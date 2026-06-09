<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Models\ApiChannel;
use App\Services\MadelineConnectionConfigurator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuthTelegram extends Command
{
    protected $signature = 'app:tg_auth
        {--api-id= : Telegram api_id (или TG_APP_ID из .env)}
        {--api-hash= : Telegram api_hash (или TG_APP_HASH из .env)}
        {--reset : Архивировать session.madeline.{api_id} и войти заново (для DC -1 / битой сессии)}';

    protected $description = 'Интерактивная авторизация MadelineProto в session.madeline.{api_id} (с MPROTO_PROXY и Redis как у парсера)';

    public function handle(): int
    {
        [$apiId, $apiHash] = $this->resolveCredentials();
        if ($apiId === null || $apiHash === null) {
            $this->error('Укажите --api-id и --api-hash, TG_APP_ID/TG_APP_HASH в .env или api_id канала в БД.');

            return self::FAILURE;
        }

        $apiId = (int) $apiId;
        $sessionName = 'session.madeline.' . $apiId;
        $sessionDir = base_path($sessionName);

        $this->warn('Перед авторизацией отключите cron с MadelineProto и завершите воркеры: pkill -f "MadelineProto worker"');
        $this->info('Сессия: ' . $sessionName);
        $this->info('MPROTO_PROXY_ENABLED: ' . (env('MPROTO_PROXY_ENABLED') ? 'true' : 'false'));

        if ($this->option('reset')) {
            $this->archiveSessionDirectory($sessionDir);
        }

        $settings = MadelineConnectionConfigurator::buildSettings($apiId, $apiHash);
        $MadelineProto = null;

        try {
            $MadelineProto = new \danog\MadelineProto\API($sessionName, $settings);
            $MadelineProto->start();

            $me = $MadelineProto->getSelf();
            if (!is_array($me)) {
                $this->error('getSelf() не вернул данные пользователя.');

                return self::FAILURE;
            }

            $label = $me['username'] ?? ($me['first_name'] ?? 'user');
            $this->info('Авторизация успешна: @' . $label . ' (id ' . ($me['id'] ?? '?') . ')');
            Log::channel('post_parser')->info('AuthTelegram success', [
                'session' => $sessionName,
                'user_id' => $me['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->error('Ошибка авторизации: ' . $e->getMessage());
            Log::channel('post_parser')->error('AuthTelegram failed', [
                'session' => $sessionName,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            if ($MadelineProto !== null) {
                try {
                    unset($MadelineProto);
                } catch (\Throwable $e) {
                    Log::channel('post_parser')->warning('AuthTelegram unset', ['message' => $e->getMessage()]);
                }
            }
            try {
                \danog\MadelineProto\API::finalize();
            } catch (\Throwable $e) {
                Log::channel('post_parser')->warning('AuthTelegram finalize', ['message' => $e->getMessage()]);
            }
        }

        $this->info('Готово. Проверка: php artisan app:tg_parse:builder');

        return self::SUCCESS;
    }

    /**
     * @return array{0: int|string|null, 1: string|null}
     */
    private function resolveCredentials(): array
    {
        $apiId = $this->option('api-id') ?: env('TG_APP_ID');
        $apiHash = $this->option('api-hash') ?: env('TG_APP_HASH');

        if ($apiId && !$apiHash) {
            $channel = ApiChannel::query()
                ->where('channel_source', ApiChannelSourceEnum::Telegram)
                ->where('status', ApiChannelStatusEnum::Active)
                ->get()
                ->first(fn (ApiChannel $ch) => (string) ($ch->options['api_id'] ?? '') === (string) $apiId);

            if ($channel && !empty($channel->options['api_hash'])) {
                $apiHash = $channel->options['api_hash'];
                $this->info('api_hash взят из канала ID ' . $channel->id);
            }
        }

        return [$apiId, $apiHash];
    }

    private function archiveSessionDirectory(string $sessionDir): void
    {
        if (!is_dir($sessionDir)) {
            return;
        }

        $archive = $sessionDir . '.broken.' . date('Y-m-d-His');
        if (!@rename($sessionDir, $archive)) {
            $this->error('Не удалось архивировать ' . $sessionDir);

            return;
        }

        $this->warn('Старая сессия перемещена в: ' . basename($archive));
        if (!mkdir($sessionDir, 0777, true) && !is_dir($sessionDir)) {
            $this->error('Не удалось создать каталог ' . $sessionDir);
        }
    }
}
