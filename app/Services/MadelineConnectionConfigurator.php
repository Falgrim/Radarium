<?php

namespace App\Services;

use danog\MadelineProto\Settings;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;

class MadelineConnectionConfigurator
{
    public static function apply(Settings $settings): void
    {
        $connection = (new Settings\Connection())
            ->setTimeout(self::intEnv('MPROTO_CONNECTION_TIMEOUT', 10));

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
