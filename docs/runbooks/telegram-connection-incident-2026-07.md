# Handoff: восстановление доступа Radarium → Telegram через Xray VPN

**Дата:** 2026-07-09 (решено 2026-08-06)  
**Статус:** ✅ Решено — VPN + переавторизация MadelineProto  
**Цель:** восстановить парсинг Telegram (`app:tg_parse:*`) после починки туннеля prod → VPS → Telegram

---

## Итог (2026-08-06)

Две отдельные поломки:

1. **VLESS/Reality prod → VPS** — handshake не завершался с `dest`/`serverName` = `www.microsoft.com` (огромный сертификат Akamai; `handshake did not complete successfully`).  
   **Фикс:** на VPS и prod сменить маскировку на `dl.google.com`; на prod `fingerprint: firefox`.  
   Проверка: `curl -x socks5h://127.0.0.1:10808 … https://api.telegram.org` → **302**; на VPS `from 77.222.58.189 accepted tcp:api.telegram.org:443`.

2. **MadelineProto IPC / битая сессия** после простоя — `The endpoint does not exist!`, гонка cron (`schedule:run` каждые 2 мин поднимал несколько `tg_parse:*`).  
   **Фикс:** отключить cron → убить процессы → `app:tg_auth --api-id=22885091 --qr --reset` (+ 2FA) → ручной `app:tg_parse:builder` OK.

### Актуально в xray-конфигах

| | Prod client | VPS server |
|--|-------------|------------|
| Reality SNI / dest | `serverName: dl.google.com` | `dest: dl.google.com:443`, `serverNames: ["dl.google.com"]` |
| fingerprint | `firefox` | — |
| UUID / shortId / keys | без изменений (совпадали) | без изменений |

### Чеклист после победы

- [ ] Вернуть `schedule:run` в crontab (если ещё закомментирован)
- [ ] На prod и VPS: `loglevel: warning`; на VPS `show: false`
- [ ] `SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=false` — не включать рассылку

---

## Контекст на момент паузы 09.07 (архив)

```
Исходная проблема: MadelineProto IPC «The endpoint does not exist!».
Корневая причина тогда: VLESS/Reality prod→VPS не поднимался
(SOCKS OK, TCP OK, ключи OK, но VPS не видел accepted от 77.222.58.189).
Следующий шаг был: debug-логи xray → см. итог 2026-08-06 выше.
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

## Текущее состояние (checkpoint 2026-08-06)

| Компонент | Статус |
|-----------|--------|
| VPS xray running | ✅ |
| VPS :443 listen | ✅ |
| VPS → Telegram | ✅ (curl 302) |
| prod nc → VPS:443 | ✅ |
| prod SOCKS inbound | ✅ |
| prod VLESS → VPS | ✅ (после смены dest на dl.google.com) |
| Reality keys | ✅ совпадают |
| MadelineProto парсинг | ✅ ручной `app:tg_parse:builder` OK после `--qr --reset` |
| Рассылка TG | выключена (`SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=false`) |

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
- runbook: `docs/RADARIUM_TECHDOC.md` §6.9, `docs/runbooks/madelineproto-vpn-routing.md`
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

- `docs/runbooks/madelineproto-vpn-routing.md` — VPN/SOCKS runbook
- `docs/RADARIUM_TECHDOC.md` §6.9 — восстановление MadelineProto
- `app/Services/ReadTelegramChats.php` — парсинг
- `app/Services/MadelineConnectionConfigurator.php` — `MPROTO_*`
- `bootstrap/app.php` — cron `app:tg_parse:specialist|builder|company`

---

## Открытые вопросы (июль 2026) — закрыты 2026-08-06

1. Почему VLESS prod→VPS не устанавливается при совпадающих ключах? → **Reality dest `www.microsoft.com`** (Akamai cert); фикс — `dl.google.com` + `fingerprint: firefox`
2. Почему в 07:06 был один успешный `accepted` на VPS, а потом нет? → туннель деградировал на маскировке microsoft; после смены dest — стабильно
3. Нужны ли IPC-правки в коде после починки VPN? → **нет**; достаточно переавторизации сессии после простоя

---

# Продолжение: инцидент 2026-08-05 (05082026)

**Дата:** 2026-08-05 (решено 2026-08-06)  
**Статус:** ✅ Решено — см. **«Итог (2026-08-06)»** в начале файла и `docs/RADARIUM_TECHDOC.md` §6.9  
**Цель:** восстановить VLESS/Reality prod → VPS, затем smoke `app:tg_parse:builder`

> Ниже — архив диагностики на момент паузы 05.08. Пункты 1–3 выполнены на другой машине; фикс — смена Reality dest на `dl.google.com` + переавторизация MadelineProto.

---

## Промпт для нового диалога в Cursor (скопировать целиком)

```
Продолжаем задачу по восстановлению доступа Radarium → Telegram через Xray VPN.

Полный контекст: docs/runbooks/telegram-connection-incident-2026-07.md
(блок «Продолжение: инцидент 2026-08-05» + исходный handoff июля).

Симптом приложения (не корневая причина):
  php artisan app:tg_parse:builder
  → getHistory: «The endpoint does not exist!»
  → «Сессия session.madeline.22885091: IPC недоступен… Пропущено каналов: 30»
Код Laravel / MadelineProto / сброс IPC — НЕ чинить, пока SOCKS→Telegram не даёт 200/301/302.

Корневая причина (доказано 2026-08-05):
  prod SOCKS 127.0.0.1:10808 принимает запросы локально,
  TCP prod → VPS 103.90.72.46:443 OK,
  VPS → api.telegram.org = 302 OK,
  Reality keys совпадают,
  НО VLESS/Reality хендшейк prod→VPS НЕ проходит:
  - curl -x socks5h://127.0.0.1:10808 … https://api.telegram.org → 000
  - TLS Client Hello уходит, ответ 0 bytes / timeout
  - на VPS journalctl при live-curl НЕТ «from 77.222.58.189 … accepted»
  - последний успешный accepted с prod на VPS: Jul 09 07:06:29

Серверы:
  Prod: 77-222-58-189 / 77.222.58.189, проект ~/www/progs.com
  VPS:  103.90.72.46 / falgrim01.fvds.ru
  Xray client SOCKS: 127.0.0.1:10808
  Сессия парсинга: session.madeline.22885091

Сделано сегодня (не повторять без нужды):
  - pkill MadelineProto, config:clear, сброс ipcState/ipc — парсинг всё равно падает
  - restart xray на prod и на VPS — curl всё равно 000
  - x25519 privateKey VPS → Password = l_JN1lPlyZShBATsDXQKT4B_XCMTSurcyTpvYYV4ZV8 (совпадает с эталоном)

НАЧНИ СРАЗУ с пунктов 1, 2, 3 ниже (конфиги → tcpdump → debug).
Не предлагай правки Laravel, пока curl через SOCKS не станет 200/301/302.
После оживления туннеля — smoke app:tg_parse:builder (шаг 5 июля / §6.9 TECHDOC).
```

---

## Хронология 2026-08-05

### A. Симптом в приложении

```text
www-root@77-222-58-189:~/www/progs.com$ php artisan app:tg_parse:builder
Подключение к Telegram…
Выборка с даты: 16:31:27 25.06.2026
getHistory … The endpoint does not exist!
Сессия session.madeline.22885091: IPC недоступен
(worker MadelineProto не подключился к Telegram DC).
Пропущено каналов: 30 [ID: 9, 54…82]
```

### B. Быстрая проверка сети на prod

| Проверка | Результат |
|----------|-----------|
| `curl -x socks5h://127.0.0.1:10808 … api.telegram.org` | **000** |
| `systemctl status xray` | active (до рестарта uptime с **2026-07-09**) |
| `ss -lntp \| grep 10808` | LISTEN `127.0.0.1:10808` |
| `pkill MadelineProto` + `config:clear` + сброс IPC `22885091` | сделано |
| повторный `app:tg_parse:builder` | снова `The endpoint does not exist!` |

**Вывод:** Laravel/IPC — следствие; SOCKS uplink мёртв.

### C. Prod после restart xray

| Проверка | Результат |
|----------|-----------|
| `nc -zv 103.90.72.46 443 -w 5` | **succeeded** |
| `curl` через SOCKS после `systemctl restart xray` | снова **000** |
| `curl -v` через SOCKS | `SOCKS5 request granted` → TLS Client Hello → **timeout 15s, 0 bytes** |
| journalctl prod | `from tcp:127.0.0.1 accepted tcp:api.telegram.org:443` и частые `…149.154.167.41:443` |

Частые обращения к `149.154.167.41` — Madeline/cron бьётся в мёртвый туннель.  
Строки `from tcp:127.0.0.1 accepted` на **prod** = только локальный SOCKS, **не** успех VLESS.

### D. Состояние VPS

| Проверка | Результат |
|----------|-----------|
| xray | active (до рестарта uptime с **2026-07-06**) |
| `ss -lntp \| grep 443` | LISTEN `*:443` |
| `curl` (без прокси) `https://api.telegram.org` | **302** |
| journalctl: последний `from 77.222.58.189 accepted` | **Jul 09 07:06:29** (`tcp:149.154.167.50:443`) |
| после этого до 05.08 | новых `accepted` с prod **нет** |

### E. Рестарт VPS + сверка ключей (выполнено)

- `sudo systemctl restart xray` на VPS — OK (Active since Wed 2026-08-05 15:06:06 CEST)
- `xray x25519 -i "mCanpwQG5U5_6OmIHri-Qc6E4I06xkIPtd5WEbaJnGM"`  
  → Password (PublicKey): **`l_JN1lPlyZShBATsDXQKT4B_XCMTSurcyTpvYYV4ZV8`** — совпадает с эталоном июля
- grep конфига на VPS показал server-side поля (`port` 443, UUID, `flow`); **полный client config на prod ещё не снят**

### F. Live-тест (оба хоста одновременно) — критический результат

**Prod:**
```bash
curl -x socks5h://127.0.0.1:10808 -m 15 -s -o /dev/null -w '%{http_code}\n' https://api.telegram.org
# → 000
```

**VPS** (`journalctl -u xray -f` в тот же момент):  
**тишина** — нет новой строки `from 77.222.58.189 … accepted`.

**Заключение:** VLESS/Reality хендшейк не устанавливается. TCP:443 жив, ключи совпадают, выход VPS в Telegram жив → нужен разбор Reality/пакетов/конфигов (не код приложения).

---

## Текущий checkpoint (2026-08-05, вечер)

| Компонент | Статус |
|-----------|--------|
| VPS xray running (после restart) | ✅ |
| VPS :443 listen | ✅ |
| VPS → Telegram | ✅ (302) |
| Reality private→public | ✅ совпадает |
| prod nc → VPS:443 | ✅ |
| prod SOCKS inbound | ✅ |
| prod VLESS → VPS | ❌ live-curl: 000 + нет accepted на VPS |
| MadelineProto парсинг | ❌ ждёт VPN |
| Полные config.json (prod + VPS) | ⏳ не сняты |
| tcpdump prod→VPS:443 | ⏳ не сделан |
| debug loglevel на prod | ⏳ не сделан |

**Эталон параметров (из июля, проверять по полным JSON):**

| Параметр | Значение |
|----------|----------|
| VPS | `103.90.72.46:443` |
| UUID | `0b7460af-59f1-403a-806b-63ea01be97a1` |
| flow | `xtls-rprx-vision` |
| serverName / dest | `www.microsoft.com` |
| publicKey | `l_JN1lPlyZShBATsDXQKT4B_XCMTSurcyTpvYYV4ZV8` |
| privateKey (VPS) | `mCanpwQG5U5_6OmIHri-Qc6E4I06xkIPtd5WEbaJnGM` |
| shortId | `6ba85179e30d4fc2` |
| fingerprint | `chrome` |
| SOCKS | `127.0.0.1:10808` |

---

## СЛЕДУЮЩИЕ ШАГИ — начать отсюда (пункты 1, 2, 3)

> Выполнять по порядку. После каждого — фиксировать вывод в этот файл или в чат.

### 1. Оба конфига целиком

**Prod:**
```bash
sudo cat /usr/local/etc/xray/config.json
```

**VPS:**
```bash
sudo cat /usr/local/etc/xray/config.json
```

Сверить глазами: UUID, flow, shortId ↔ shortIds[], serverName ↔ serverNames[], publicKey/privateKey, address `103.90.72.46:443`, listen `:443`.

### 2. Видит ли VPS TCP с prod во время curl

**VPS** (одно окно):
```bash
sudo tcpdump -ni any host 77.222.58.189 and port 443 -c 20
```

**Prod** (в тот же момент):
```bash
curl -x socks5h://127.0.0.1:10808 -m 15 -s -o /dev/null -w '%{http_code}\n' https://api.telegram.org
```

| tcpdump | Вывод |
|---------|-------|
| есть пакеты `77.222.58.189 → VPS:443` | TCP доходит → ломается Reality/конфиг |
| пусто | фильтрация / не тот маршрут (редко при живом `nc`) |

### 3. Debug на prod + проверка camouflage dest на VPS

**Prod:**
```bash
sudo python3 - <<'PY'
import json
p="/usr/local/etc/xray/config.json"
c=json.load(open(p))
c.setdefault("log",{})["loglevel"]="debug"
json.dump(c, open(p,"w"), indent=2)
print("ok")
PY
sudo systemctl restart xray
# окно 1:
sudo journalctl -u xray -f --no-pager
# окно 2:
curl -x socks5h://127.0.0.1:10808 -m 15 -v https://api.telegram.org -o /dev/null
```

Искать в debug: `failed` / `reality` / `rejected` / `dial` / `timeout`.  
После диагностики вернуть `"loglevel": "warning"` и `systemctl restart xray`.

**VPS:**
```bash
curl -m 10 -s -o /dev/null -w '%{http_code}\n' https://www.microsoft.com
```

### После пунктов 1–3 (если туннель всё ещё мёртв)

Возможные направления (не делать вслепую до 1–3):
- явный `tag` + `routing` на prod (см. шаг 2 июля выше);
- смена SNI/dest / перегенерация Reality shortId+ключей;
- проверка с другой сети тем же VLESS-клиентом (исключить DPI хостера prod);
- смена VPS/транспорта.

### Когда туннель оживёт (критерии)

1. На VPS при curl с prod: `from 77.222.58.189:… accepted tcp:api.telegram.org:443` (или DC IP)
2. На prod: `curl -x socks5h://127.0.0.1:10808 …` → **200 / 301 / 302**
3. Затем smoke:
```bash
# prod, ~/www/progs.com
pkill -f "MadelineProto worker" 2>/dev/null
rm -f session.madeline.22885091/ipcState.php
find session.madeline.22885091 -maxdepth 1 \( -name 'ipc' -o -name 'callback.ipc' \) -delete
php artisan config:clear
php artisan app:tg_parse:builder
```
4. Если VPN OK, а парсинг всё ещё падает с `DC -1` / битой сессией → `app:tg_auth --api-id=22885091 --api-hash=… --qr --reset` (TECHDOC §6.9). **Не** FLUSHDB Redis, **не** подменять старый `safe.php`.

---

## Что не делать

- Не править Laravel/IPC «вслепую», пока SOCKS curl ≠ 200/301/302
- Не включать `SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED` на prod
- Не путать prod-лог `from tcp:127.0.0.1 accepted` с успехом туннеля
- Не считать рестарт xray достаточным без live-теста на VPS

---

*Обновлено 2026-08-05 для handoff между машинами Cursor. Продолжать с пунктов 1–3.*
