<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Models\ApiChannel;
use App\Services\MadelineConnectionConfigurator;
use App\Services\MadelineSessionIpcCleaner;
use danog\MadelineProto\API;
use danog\MadelineProto\TL\Types\LoginQrCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuthTelegram extends Command
{
    protected $signature = 'app:tg_auth
        {--api-id= : Telegram api_id (или TG_APP_ID из .env)}
        {--api-hash= : Telegram api_hash (или TG_APP_HASH из .env)}
        {--qr : Вход по QR-коду (Telegram → Устройства → Подключить устройство)}
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
            $MadelineProto = new API($sessionName, $settings);

            if ($this->option('qr')) {
                $this->loginViaQr($MadelineProto);
            } else {
                $MadelineProto->start();
            }

            $this->complete2faIfNeeded($MadelineProto);

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
                'qr' => (bool) $this->option('qr'),
            ]);
        } catch (\Throwable $e) {
            $this->error('Ошибка авторизации: ' . $e->getMessage());
            Log::channel('post_parser')->error('AuthTelegram failed', [
                'session' => $sessionName,
                'message' => $e->getMessage(),
                'qr' => (bool) $this->option('qr'),
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
                API::finalize();
            } catch (\Throwable $e) {
                Log::channel('post_parser')->warning('AuthTelegram finalize', ['message' => $e->getMessage()]);
            }
            MadelineSessionIpcCleaner::clear($sessionName);
        }

        $this->info('Готово. Проверка: php artisan app:tg_parse:builder');

        return self::SUCCESS;
    }

    private function loginViaQr(API $api): void
    {
        if ($api->getAuthorization() === API::LOGGED_IN) {
            $this->info('Уже авторизован.');

            return;
        }

        $this->info('QR-вход: в Telegram на телефоне откройте Настройки → Устройства → Подключить устройство.');
        $this->comment('Сканируйте QR ниже или откройте ссылку tg://login?... на том же аккаунте.');

        $qr = $api->qrLogin();
        while ($qr instanceof LoginQrCode) {
            $this->displayQrCode($qr);
            $this->comment('Ожидание сканирования… (истекает через ' . $qr->expiresIn() . ' с)');

            $qr = $qr->waitForLoginOrQrCodeExpiration();

            if ($qr instanceof LoginQrCode) {
                $this->warn('QR истёк, показываем новый.');
            }
        }

        if ($api->getAuthorization() === API::WAITING_PASSWORD) {
            return;
        }

        if ($api->getAuthorization() !== API::LOGGED_IN) {
            throw new \RuntimeException(
                'QR-вход не завершён. Состояние авторизации: ' . $api->getAuthorization()
            );
        }

        $this->info('QR-код принят, вход выполнен.');
    }

    private function displayQrCode(LoginQrCode $qr): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Ссылка:</> ' . $qr->link);
        $this->newLine();
        $this->line($qr->getQRText(1));
        $this->newLine();
    }

    private function complete2faIfNeeded(API $api): void
    {
        if ($api->getAuthorization() !== API::WAITING_PASSWORD) {
            return;
        }

        $password = $this->secret('Облачный пароль 2FA Telegram: ');
        if ($password === null || $password === '') {
            throw new \RuntimeException('Для этого аккаунта требуется облачный пароль 2FA.');
        }

        $api->complete2faLogin($password);
        $this->info('2FA принят.');
    }

    /**
     * @return array{0: int|string|null, 1: string|null}
     */
    private function resolveCredentials(): array
    {
        $apiId = $this->option('api-id') ?: env('TG_APP_ID');
        $apiHash = $this->option('api-hash');

        if ($apiId && !$apiHash) {
            $apiHash = $this->resolveApiHashFromChannel($apiId) ?: env('TG_APP_HASH');
        } elseif (!$apiHash) {
            $apiHash = env('TG_APP_HASH');
        }

        return [$apiId, $apiHash];
    }

    private function resolveApiHashFromChannel(int|string $apiId): ?string
    {
        $channel = ApiChannel::query()
            ->where('channel_source', ApiChannelSourceEnum::Telegram)
            ->where('status', ApiChannelStatusEnum::Active)
            ->get()
            ->first(fn (ApiChannel $ch) => (string) ($ch->options['api_id'] ?? '') === (string) $apiId);

        if ($channel && !empty($channel->options['api_hash'])) {
            $this->info('api_hash взят из канала ID ' . $channel->id);

            return $channel->options['api_hash'];
        }

        return null;
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
