<?php

namespace App\Services;

use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;

class MadelineConnectionConfigurator
{
    /**
     * Файловый лог в storage (не в cwd), чтобы дочерние IPC-процессы не писали MadelineProto.log в корень проекта.
     */
    public static function applyFileLogger(Settings $settings, int $level = Logger::LEVEL_ERROR): void
    {
        $loggerSettings = new LoggerSettings();
        $loggerSettings->setType(Logger::FILE_LOGGER);
        $loggerSettings->setLevel($level);
        $loggerSettings->setExtra(storage_path('logs/MadelineProto.log'));
        $settings->setLogger($loggerSettings);
    }

    public static function buildSettings(int|string $apiId, string $apiHash, int $loggerLevel = Logger::LEVEL_ERROR): Settings
    {
        $settings = new Settings();
        $settings->setAppInfo(
            (new Settings\AppInfo())
                ->setApiId($apiId)
                ->setApiHash($apiHash)
                ->setLangCode('RU')
        );

        self::applyFileLogger($settings, $loggerLevel);

        $redis = (new Settings\Database\Redis())
            ->setUri('redis://' . config('database.redis.default.host'));

        if (config('database.redis.default.password')) {
            $redis->setPassword(config('database.redis.default.password'));
        }

        $settings->setDb($redis);
        self::apply($settings);

        return $settings;
    }

    public static function apply(Settings $settings): void
    {
        $connection = (new Settings\Connection())
            ->setTimeout(self::intEnv('MPROTO_CONNECTION_TIMEOUT', 10))
            ->setIpv6(self::boolEnv('MPROTO_IPV6_ENABLED', false));

        if (self::boolEnv('MPROTO_PROXY_ENABLED', false)) {
            $proxyConfig = [
                'address' => env('MPROTO_PROXY_HOST', '127.0.0.1'),
                'port' => self::intEnv('MPROTO_PROXY_PORT', 10808),
            ];

            $username = env('MPROTO_PROXY_USER');
            $password = env('MPROTO_PROXY_PASS');

            if (!empty($username)) {
                $proxyConfig['username'] = $username;
            }

            if (!empty($password)) {
                $proxyConfig['password'] = $password;
            }

            $proxyType = strtolower((string) env('MPROTO_PROXY_TYPE', 'socks5'));
            $proxyClass = $proxyType === 'http' ? HttpProxy::class : SocksProxy::class;
            $connection->addProxy($proxyClass, $proxyConfig);
        }

        $settings->setConnection($connection);
        $settings->setSerialization(
            (new Settings\Serialization())
                ->setInterval(self::intEnv('MPROTO_SERIALIZATION_INTERVAL', 30))
        );
    }

    private static function intEnv(string $key, int $default): int
    {
        $value = env($key, $default);

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    private static function boolEnv(string $key, bool $default): bool
    {
        $value = env($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
