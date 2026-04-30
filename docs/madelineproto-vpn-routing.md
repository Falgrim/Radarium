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
- `MPROTO_IPV6_ENABLED` — если `false` (значение по умолчанию в коде), вызывается `Settings\Connection::setIpv6(false)`, чтобы MadelineProto не ходил в DC по IPv6 (полезно при таймаутах AAAA/DC и работе через SOCKS).

Если `MPROTO_PROXY_ENABLED=true`, в `MadelineProto` добавляется proxy через `Settings\Connection::addProxy(...)`.

Логгер и соединение задаются в объекте `Settings` до вызова `new \danog\MadelineProto\API($session, $settings)`. После конструктора **`updateSettings` не вызывается** — иначе при IPC возможна гонка (`MTProto::$logger must not be accessed before initialization`); MadelineProto сам ставит merge настроек в очередь при подключении.

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
MPROTO_IPV6_ENABLED=false
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

## Чеклист: только IPv6-off на проде (после деплоя кода)

Цель: убедиться, что отключение IPv6 в настройках MadelineProto устранило `Could not connect to DC` при уже рабочем SOCKS.

1. **Env**: `MPROTO_PROXY_ENABLED=true`, SOCKS проверяется как раньше (`curl --socks5-hostname 127.0.0.1:10808 https://api.telegram.org`).
2. **IPv6**: `MPROTO_IPV6_ENABLED=false` или переменная не задана (в коде дефолт — выключено).
3. **Конфиг Laravel**: `php artisan config:clear` и при необходимости `php artisan config:cache`.
4. **Перезапуск долгоживущих процессов**, держащих Madeline (supervisor/systemd unit очереди или отдельный воркер парсинга) — чтобы подтянулись новые настройки.
5. **Smoke**: `php artisan app:tg_parse:builder` (и при необходимости `company` / `specialist`).
6. **Логи**: `storage/logs/MadelineProto.log` — нет повторяющихся попыток IPv6 к DC, если раньше они были заметны; нет стабильной ошибки `Could not connect to DC`.
7. **Регрессии**: короткая проверка интеграций (Robokassa, VK, Tubus), не завязанных на Telegram DC.

**Откат IPv6-only**: выставить `MPROTO_IPV6_ENABLED=true` или откатить релиз; прокси-переменные не трогать.

---

## Fallback: Xray TUN + селективная маршрутизация только сетей Telegram

Использовать, если после `MPROTO_IPV6_ENABLED=false` ошибка DC сохраняется, а прямой TCP с VPS до Telegram по-прежнему недоступен.

Официально: inbound `tun` в Xray **только поднимает интерфейс**; адреса, таблицы маршрутизации и policy routing настраивает **ОС**. См. [Xray proxy/tun README](https://github.com/XTLS/Xray-core/blob/main/proxy/tun/README.md). Нельзя отправлять default `0.0.0.0/0` через `xray0` без **отдельного host-маршрута** на IP uplink VLESS через обычный шлюз — иначе петля и обрыв сети.

**Важно**: офисный Ollama и прочие цели в частных сетях не должны попадать под маршруты в `xray0`. Не добавляйте RFC1918 в маршруты через TUN; default route не меняйте.

### A. Root preflight: `/dev/net/tun`, модуль, unit

Выполнять под root (или `sudo`). Иметь второй канал (KVM/консоль хостера) на случай ошибки маршрутов.

```bash
ls -l /dev/net/tun
lsmod | grep -E '^tun\s' || true
modprobe tun
lsmod | grep -E '^tun\s'
```

Сохранить состояние для отката:

```bash
ip route > /root/ip-route.before-tun.txt
ip rule > /root/ip-rule.before-tun.txt
```

Если Xray в systemd:

```bash
journalctl -u xray -b --no-pager
```

Искать `Operation not permitted`, `cannot create tun` — часто не хватает `CAP_NET_ADMIN` или мешает профиль (AppArmor). На VPS в LXC/OpenVZ TUN может быть запрещён хостингом — смотреть логи Xray и сообщения ядра.

### B. Идея конфига Xray: `tun` + тот же VLESS/Reality + direct по умолчанию

- **inbounds**: `protocol: "tun"`, `settings.name` (например `xray0`), `port: 0` (как в README).
- **outbounds**: ваш существующий VLESS/Reality (tag `proxy`) + `freedom` с tag `direct`.
- **routing**: последним правилом — весь остальной трафик на `direct`; трафик из TUN к префиксам Telegram — на `proxy` (по `inboundTag` + `ip`, см. документацию [routing](https://xtls.github.io/en/config/routing.html)).

Доменные правила для трафика из TUN менее надёжны (трафик уже «IP к IP»); для «только Telegram» опирайтесь на **список IP-префиксов** DC (актуальный список — в документации Telegram / `geoip:telegram` в связке с пониманием ограничений).

Скелет (подставьте свой VLESS/Reality из рабочего клиентского конфига выше; блок `rules` дополните реальными префиксами или `geoip:telegram`):

```json
{
  "log": { "loglevel": "warning" },
  "inbounds": [
    {
      "tag": "tun-in",
      "port": 0,
      "protocol": "tun",
      "settings": { "name": "xray0", "MTU": 1492 }
    }
  ],
  "outbounds": [
    {
      "tag": "proxy",
      "protocol": "vless",
      "settings": { },
      "streamSettings": { }
    },
    { "tag": "direct", "protocol": "freedom", "settings": {} }
  ],
  "routing": {
    "domainStrategy": "AsIs",
    "rules": [
      {
        "type": "field",
        "inboundTag": ["tun-in"],
        "ip": ["geoip:telegram"],
        "outboundTag": "proxy"
      },
      { "type": "field", "outboundTag": "direct" }
    ]
  }
}
```

Поле `settings` / `streamSettings` у outbound `proxy` скопируйте из уже работающего inbound SOCKS-конфига (тот же `vnext`, `realitySettings` и т.д.).

### C. Маршруты ОС: только префиксы Telegram → `dev xray0`, uplink — через обычный шлюз

Подставьте: `UPLINK_IP` — IP или резолв вашего VLESS-сервера; `GW` — шлюз основного интерфейса (`ip route | grep default`).

```bash
ip route replace UPLINK_IP/32 via GW
# для каждого префикса Telegram (IPv4), после поднятия xray0:
ip route replace TELEGRAM_PREFIX dev xray0
```

Префиксы взять из официального списка Telegram (datacenter IP ranges). Скрипт с перечислением префиксов + systemd oneshot упрощают включение/выключение.

**Откат TUN**: остановить Xray с TUN-конфигом; удалить добавленные `ip route` / `ip rule` / nftables; восстановить из сохранённых файлов при необходимости.

### D. Верификация и связка с MadelineProto

```bash
ip route get TELEGRAM_DC_IP
```

Ожидается выход через `dev xray0` (или через отдельную таблицу policy routing — как настроите).

После стабилизации маршрутов до DC:

- либо оставить `MPROTO_PROXY_ENABLED=true` (предсказуемо, но цепочка app → SOCKS → Xray);
- либо выключить SOCKS в `.env` (`MPROTO_PROXY_ENABLED=false`) и полагаться на kernel routing в TUN — **только** если `ip route get` до DC и `app:tg_parse:builder` стабильно успешны без SOCKS.

Проверка парсинга:

```bash
php artisan app:tg_parse:builder
tail -n 120 storage/logs/MadelineProto.log
```
