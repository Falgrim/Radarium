# Handoff: восстановление доступа Radarium → Telegram через Xray VPN

**Дата:** 2026-07-09  
**Статус:** ⏸ Пауза — инфраструктура VPN, не код Laravel  
**Цель:** восстановить парсинг Telegram (`app:tg_parse:*`) после починки туннеля prod → VPS → Telegram

---

## Контекст для новой задачи (промпт)

```
Продолжаем задачу по восстановлению парсинга Telegram в Radarium.

Исходная проблема: в логах post_parser ошибки MadelineProto IPC
(«The endpoint does not exist!», abort group on IPC loss).

Расследование показало: корневая причина — недоступность VPN-туннеля
prod → VPS (Xray VLESS/Reality), через который MadelineProto выходит в Telegram.

VPS провайдер чинил доступность. Сейчас:
- VPS жив, 443 открыт, VPS сам до Telegram достучался (curl → 302)
- TCP prod → VPS:443 работает (nc succeeded)
- SOCKS prod (127.0.0.1:10808) принимает соединения
- НО VLESS-туннель prod → VPS НЕ устанавливается:
  при curl через SOCKS на VPS НЕ появляются строки
  «from 77.222.58.189:... accepted»
- publicKey Reality проверен — СОВПАДАЕТ с VPS privateKey

Изменения в коде Laravel по IPC были откачены (преждевременно).
Оставлено только: SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=false по умолчанию
(рассылка на prod навсегда выключена).

Следующий шаг: debug-логи xray на prod во время curl,
при необходимости tag+routing в config.json, затем smoke парсинга.

Полный контекст: DOC/tg_connection_problem_july09.md
```

---

## Серверы

| Роль | Хост | Примечание |
|------|------|------------|
| **Prod (Radarium)** | `77-222-58-189` / `77-222-58-189.swtest.ru` | Laravel, MadelineProto, xray-**клиент** |
| **VPS (VPN)** | `103.90.72.46` / `falgrim01.fvds.ru` | xray-**сервер** VLESS+Reality :443 |
| Проект на prod | `~/www/progs.com` или `/var/www/www-root/data/www/progs.com` | |

**Часовые пояса:** prod MSK (UTC+3), VPS CEST (UTC+2) — разница 1 час, это нормально.

---

## Архитектура

```
MadelineProto / curl
    → SOCKS 127.0.0.1:10808 (xray inbound на prod)
    → VLESS + Reality outbound → 103.90.72.46:443 (xray на VPS)
    → freedom/direct на VPS → Telegram DC (149.154.167.xx:443)
```

**Laravel:** `MPROTO_PROXY_ENABLED=true`, SOCKS `127.0.0.1:10808`  
(`MadelineConnectionConfigurator`, `ReadTelegramChats`)

**Рассылка:** `SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=false` всегда на prod — не трогать, не включать в runbook.

---

## Хронология расследования

### 1. Исходные логи (2026-06-29 / 30)

- `ReadTelegramChats getHistory` → `The endpoint does not exist!`
- `IPC loss, retry session` → `abort group on IPC loss` (пропуск 20+ каналов)
- Сессия: `session.madeline.22885091`

### 2. Первая гипотеза — код

- Были подготовлены правки IPC/retry/`app:tg_parse:all` — **откачены**
- Причина отката: VPS был недоступен, правки кода не помогли бы

### 3. Диагностика сети

| Проверка | Результат |
|----------|-----------|
| `nc` prod → VPS:443 (раньше) | timeout |
| `nc` prod → VPS:443 (позже) | **succeeded** |
| `curl` VPS → api.telegram.org | **302** |
| `curl` prod через SOCKS | **000** / TLS timeout |
| ping + Test-NetConnection с домашней Windows | VPS доступен, 443 OK |

### 4. Ключевое открытие

- **Prod journalctl** `from tcp:127.0.0.1 accepted tcp:api.telegram.org:443` — это только **SOCKS inbound**, не успех туннеля
- **VPS journalctl** `from 77.222.58.189:... accepted` — реальный VLESS-клиент с prod
- При curl ~18:18–18:26 MSK на VPS **новых** строк от `77.222.58.189` **нет**
- Единственная успешная запись на VPS за день: `Jul 09 07:06:29 ... from 77.222.58.189 accepted`

**Вывод:** SOCKS на prod OK, TCP до VPS OK, **VLESS/Reality prod→VPS сейчас не работает**.

---

## Конфигурация (проверено, совпадает)

### Prod client — `/usr/local/etc/xray/config.json`

- **Inbound:** SOCKS `127.0.0.1:10808`, udp true
- **Outbound:** VLESS → `103.90.72.46:443`
- **UUID:** `0b7460af-59f1-403a-806b-63ea01be97a1`
- **flow:** `xtls-rprx-vision`
- **Reality client:**
  - `serverName`: `www.microsoft.com`
  - `publicKey`: `l_JN1lPlyZShBATsDXQKT4B_XCMTSurcyTpvYYV4ZV8` ✅ проверен
  - `shortId`: `6ba85179e30d4fc2`
  - `fingerprint`: `chrome`
- **Нет:** `tag` на outbound, блока `routing`
- **loglevel:** `warning`

### VPS server — `/usr/local/etc/xray/config.json`

- **Inbound:** VLESS :443, listen `0.0.0.0`
- **UUID + flow:** те же
- **Reality server:**
  - `dest`: `www.microsoft.com:443`
  - `serverNames`: `["www.microsoft.com"]`
  - `privateKey`: `mCanpwQG5U5_6OmIHri-Qc6E4I06xkIPtd5WEbaJnGM`
  - `shortIds`: `["6ba85179e30d4fc2"]`

### Проверка publicKey (выполнено 2026-07-09)

```bash
# на VPS
/usr/local/bin/xray x25519 -i "mCanpwQG5U5_6OmIHri-Qc6E4I06xkIPtd5WEbaJnGM"
# Password: l_JN1lPlyZShBATsDXQKT4B_XCMTSurcyTpvYYV4ZV8
```

**Совпадает с prod `publicKey`** — ключи НЕ причина.

---

## Текущее состояние (checkpoint)

| Компонент | Статус |
|-----------|--------|
| VPS xray running | ✅ |
| VPS :443 listen | ✅ |
| VPS → Telegram | ✅ (curl 302) |
| prod nc → VPS:443 | ✅ |
| prod SOCKS inbound | ✅ |
| prod VLESS → VPS | ❌ (VPS не видит 77.222.58.189 при curl) |
| Reality keys | ✅ совпадают |
| MadelineProto парсинг | ❌ ждёт VPN |
| Код Laravel (IPC fixes) | откачен |
| Рассылка TG | выключена навсегда |

---

## План восстановления (следующие шаги)

### Шаг 1 — Debug-логи на prod (ПРИОРИТЕТ)

1. В `/usr/local/etc/xray/config.json` на prod:
   ```json
   "log": { "loglevel": "debug" }
   ```
2. `sudo systemctl restart xray`
3. Терминал 1: `sudo journalctl -u xray -f --no-pager`
4. Терминал 2: `curl -x socks5h://127.0.0.1:10808 -m 10 https://api.telegram.org -o /dev/null`
5. Параллельно на VPS: `sudo journalctl -u xray -f --no-pager`
6. Искать на prod: `reality`, `dial`, `failed`, `error`, `timeout`, `reset`
7. После диагностики вернуть `"loglevel": "warning"`

### Шаг 2 — Явный routing на prod (если debug неясный)

Добавить в prod config:

- `"tag": "proxy"` на VLESS outbound
- блок `routing` с `"outboundTag": "proxy"` для tcp,udp

```bash
sudo /usr/local/bin/xray run -test -config /usr/local/etc/xray/config.json
sudo systemctl restart xray
```

### Шаг 3 — Рестарт обоих xray

```bash
# VPS
sudo systemctl restart xray

# prod
sudo systemctl restart xray
sleep 3
curl -x socks5h://127.0.0.1:10808 -m 10 -s -o /dev/null -w '%{http_code}\n' https://api.telegram.org
```

Ожидание: **200 / 301 / 302**

### Шаг 4 — Критерии успеха туннеля

**На VPS** при curl с prod:

```
from 77.222.58.189:XXXXX accepted tcp:api.telegram.org:443
```

**На prod:**

```bash
curl -x socks5h://127.0.0.1:10808 -m 10 -s -o /dev/null -w '%{http_code}\n' https://api.telegram.org
# 200 / 301 / 302

curl -x socks5h://127.0.0.1:10808 -m 15 -vk https://149.154.167.50 -H "Host: api.telegram.org"
# TLS handshake OK
```

### Шаг 5 — Smoke парсинга (только после Шага 4)

```bash
# на prod
pkill -f "MadelineProto worker" 2>/dev/null
rm -f session.madeline.22885091/ipcState.php
find session.madeline.22885091 -maxdepth 1 \( -name 'ipc' -o -name 'callback.ipc' \) -delete

php artisan config:clear
php artisan app:tg_parse:builder
# или specialist / company / все три по cron

tail -n 50 storage/logs/post_parser.log
```

### Шаг 6 — Если туннель OK, парсинг всё ещё падает

Тогда смотреть нашу сторону (не VPN):

- сессия `session.madeline.22885091` — переавторизация `app:tg_auth --qr --reset`
- runbook: `DOC/RADARIUM_TECHDOC.md` §6.9, `docs/madelineproto-vpn-routing.md`
- IPC-правки в коде **не деплоились** — обсуждать заново только при подтверждённом VPN

---

## Команды-шпаргалка

### Prod

```bash
nc -zv 103.90.72.46 443 -w 5
curl -x socks5h://127.0.0.1:10808 -m 10 -s -o /dev/null -w '%{http_code}\n' https://api.telegram.org
curl -v -x socks5h://127.0.0.1:10808 -m 15 https://api.telegram.org 2>&1 | tail -20
sudo journalctl -u xray -n 30 --no-pager
sudo systemctl restart xray
sudo cat /usr/local/etc/xray/config.json
grep MPROTO_ .env
```

### VPS

```bash
systemctl status xray --no-pager
ss -lntp | grep 443
curl -m 10 -s -o /dev/null -w '%{http_code}\n' https://api.telegram.org
sudo journalctl -u xray -f --no-pager
/usr/local/bin/xray x25519 -i "mCanpwQG5U5_6OmIHri-Qc6E4I06xkIPtd5WEbaJnGM"
```

### Windows (проверка VPS с дома)

```powershell
ping 103.90.72.46
Test-NetConnection 103.90.72.46 -Port 443
```

---

## Важные различия в логах

| Лог | Значение |
|-----|----------|
| prod: `from tcp:127.0.0.1 accepted tcp:...` | SOCKS принял запрос локально |
| VPS: `from 77.222.58.189 accepted tcp:...` | VLESS-туннель реально работает |

**Не путать!** Первое без второго = туннель не поднялся.

---

## Состояние репозитория (код)

- Ветка на момент паузы: `feature/builders-newBdFields`
- IPC-изменения (`ReadTelegramChats`, `ParseTelegramAllChats`, `MPROTO_IPC_*`) — **откачены**
- Осталось незакоммиченным (опционально):
  - `.env.example`: `SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=false`
  - `bootstrap/app.php`: дефолт `false` для рассылки

---

## Ссылки в проекте

- `docs/madelineproto-vpn-routing.md` — VPN/SOCKS runbook
- `DOC/RADARIUM_TECHDOC.md` §6.9 — восстановление MadelineProto
- `app/Services/ReadTelegramChats.php` — парсинг
- `app/Services/MadelineConnectionConfigurator.php` — `MPROTO_*`
- `bootstrap/app.php` — cron `app:tg_parse:specialist|builder|company`

---

## Открытые вопросы

1. Почему VLESS prod→VPS не устанавливается при совпадающих ключах? → ждём debug-лог prod
2. Почему в 07:06 был один успешный `accepted` на VPS, а потом нет? → intermittent / залипший xray / сетевая фильтрация
3. Нужны ли IPC-правки в коде после починки VPN? → решать после успешного `app:tg_parse:builder`

---

*Файл создан для handoff между машинами. Обновлять по мере прогресса.*
