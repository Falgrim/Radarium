# MadelineProto через VPN (VLESS/Reality + локальный SOCKS)

Этот сценарий направляет только трафик `MadelineProto` через VPN-туннель, не переводя весь сервер в full-tunnel.

## Что уже поддержано в коде

Сервисы `ReadTelegramChats`, `SendMessageTelegram` и команда `app:tg_profile:photo` читают переменные:

- `MPROTO_PROXY_ENABLED`
- `MPROTO_PROXY_TYPE` (`socks5` или `http`)
- `MPROTO_PROXY_HOST`
- `MPROTO_PROXY_PORT`
- `MPROTO_PROXY_USER`
- `MPROTO_PROXY_PASS`
- `MPROTO_CONNECTION_TIMEOUT`
- `MPROTO_SERIALIZATION_INTERVAL`

Если `MPROTO_PROXY_ENABLED=true`, в `MadelineProto` добавляется proxy через `Settings\Connection::addProxy(...)`.

## Этап 1. Поднять xray-клиент на проде

Ниже пример клиентского конфига `xray`:

```json
{
  "inbounds": [
    {
      "tag": "local-socks",
      "listen": "127.0.0.1",
      "port": 10808,
      "protocol": "socks",
      "settings": {
        "auth": "noauth",
        "udp": true
      }
    }
  ],
  "outbounds": [
    {
      "tag": "vless-reality-out",
      "protocol": "vless",
      "settings": {
        "vnext": [
          {
            "address": "YOUR_VPS_IP_OR_DOMAIN",
            "port": 443,
            "users": [
              {
                "id": "YOUR_UUID",
                "encryption": "none",
                "flow": "xtls-rprx-vision"
              }
            ]
          }
        ]
      },
      "streamSettings": {
        "network": "tcp",
        "security": "reality",
        "realitySettings": {
          "serverName": "YOUR_SERVER_NAME",
          "publicKey": "YOUR_PUBLIC_KEY",
          "shortId": "YOUR_SHORT_ID"
        }
      }
    }
  ]
}
```

Проверки:

```bash
systemctl restart xray
systemctl status xray --no-pager
ss -lntp | rg 10808
```

## Этап 2. Настроить .env на проде

```env
MPROTO_PROXY_ENABLED=true
MPROTO_PROXY_TYPE=socks5
MPROTO_PROXY_HOST=127.0.0.1
MPROTO_PROXY_PORT=10808
MPROTO_PROXY_USER=
MPROTO_PROXY_PASS=
MPROTO_CONNECTION_TIMEOUT=10
MPROTO_SERIALIZATION_INTERVAL=30
```

Применить конфиг:

```bash
php artisan config:clear
php artisan config:cache
```

## Этап 3. Короткое окно внедрения (5-10 минут)

```bash
php artisan down
php artisan schedule:interrupt
php artisan queue:restart
systemctl restart xray
php artisan config:clear
php artisan config:cache
php artisan app:tg_parse:builder
php artisan app:tg_parse:company
php artisan app:tg_parse:specialist
php artisan up
```

## Этап 4. Проверка результата

Проверить, что нет ошибок `Could not connect to DC`:

```bash
php artisan app:tg_parse:builder
```

Логи:

```bash
tail -n 120 storage/logs/MadelineProto.log
tail -n 120 storage/logs/post_parser.log
```

## Rollback (быстрый откат)

1) Выключить прокси:

```env
MPROTO_PROXY_ENABLED=false
```

2) Применить config:

```bash
php artisan config:clear
php artisan config:cache
```

3) Остановить xray при необходимости:

```bash
systemctl stop xray
```

4) Проверочный прогон:

```bash
php artisan app:tg_parse:builder
```
