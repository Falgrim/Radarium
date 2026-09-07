# Очередь Laravel на production (queue:work)

Runbook для сервера **progs.com**: зачем нужен воркер, как его поднять и как разбирать зависшие задачи.

## Что зависит от воркера

| Работает без воркера | Требует воркера |
|----------------------|-----------------|
| Парсинг источников и разбор очереди ИИ по cron (`app:tg_parse:*`, `app:ai_parse:*` каждые 30 минут) | Кнопка «Обработать необработанные сообщения» в админке «Источники сообщений» |
| Ручные запуски `php artisan app:ai_parse:builder` и других команд | Смена сервиса ИИ и массовая установка статуса `InQueue` в разделе «Сообщения/Посты» |
| Диагностика `app:catalog:diagnose-builders` | `app:ai_parse:requeue` без флага `--no-dispatch` (фоновый добор очереди) |

Единственное задание проекта — `App\Jobs\ProcessPendingAiPosts`: оно вызывает `app:ai_parse:*` партиями по 100 сообщений и ставит себя в очередь заново, пока есть необработанные посты. Если воркера нет, такие задания просто лежат в таблице `jobs` (в диагностике это секция 12 «Очередь Laravel»: растущий `jobs_pending` со старым `oldest_job`).

## Проверка состояния

```bash
ps aux | grep "[q]ueue:work"
php artisan queue:monitor database:default
php artisan queue:failed
```

## Обязательная подготовка: таймауты

Задание объявляет `timeout = 3600` и `tries = 1`, а значения по умолчанию для очереди намного меньше. Без правки настроек воркер убьёт разбор очереди на первой же минуте, повторной попытки не будет.

1. **Таймаут воркера.** У `queue:work` по умолчанию 60 секунд, поэтому запускать его нужно только с явным `--timeout=3700`.
2. **`retry_after` соединения.** В `config/queue.php` у драйвера `database` это `env('DB_QUEUE_RETRY_AFTER', 90)`, и в `.env` параметр не задан. Значение обязано быть **больше** таймаута задания, иначе через 90 секунд очередь сочтёт задание потерянным и выдаст его второму воркеру — разбор пойдёт в две параллельные копии (`ShouldBeUniqueUntilProcessing` снимает блокировку в момент начала обработки и от этого не защищает).

Перед первым запуском добавьте в `.env`:

```env
DB_QUEUE_RETRY_AFTER=3700
```

и примените: `php artisan config:clear` (или `sh gitupdate.sh`).

## Способ 1: cron + flock (рекомендуется, root не нужен)

```bash
crontab -e
```

```cron
* * * * * flock -n /tmp/radarium-queue.lock php /var/www/www-root/data/www/progs.com/artisan queue:work --stop-when-empty --timeout=3700 --sleep=5 >> /var/www/www-root/data/www/progs.com/storage/logs/queue.log 2>&1
```

Воркер поднимается раз в минуту, разбирает всё, что есть в очереди, и завершается. `flock -n` не даёт запустить второй экземпляр, пока работает первый. Плюс способа: после каждого деплоя процесс стартует уже с новым кодом, отдельный `queue:restart` не нужен.

## Способ 2: systemd (постоянный процесс, нужен root)

`/etc/systemd/system/radarium-queue.service`:

```ini
[Unit]
Description=Radarium queue worker
After=network.target mysql.service

[Service]
User=www-root
Group=www-root
WorkingDirectory=/var/www/www-root/data/www/progs.com
ExecStart=/usr/bin/php artisan queue:work --timeout=3700 --sleep=5 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now radarium-queue
systemctl status radarium-queue
journalctl -u radarium-queue -n 50
```

`--max-time=3600` здесь обязателен: воркер держит код в памяти, поэтому раз в час процесс должен завершаться, чтобы `Restart=always` поднял его с актуальной версией.

## Способ 3: разовый запуск (проверить, что задания разбираются)

```bash
cd ~/www/progs.com
nohup php artisan queue:work --stop-when-empty --timeout=3700 >> storage/logs/queue.log 2>&1 &
tail -f storage/logs/queue.log
```

## После каждого деплоя

`gitupdate.sh` делает `artisan optimize` и `schedule:interrupt`, но **не перезапускает воркер**. Для постоянного процесса (способ 2) добавляйте:

```bash
php artisan queue:restart
```

Без этого воркер продолжит выполнять код, загруженный при старте.

## Разбор проблем

| Симптом | Причина | Действие |
|---------|---------|----------|
| `jobs_pending` растёт, `oldest_job` давний | воркер не запущен | поднять по способу 1 или 2 |
| Задание пропадает через минуту, разбор не двигается | запуск без `--timeout` | перезапустить с `--timeout=3700` |
| Один и тот же разбор идёт в двух копиях | `DB_QUEUE_RETRY_AFTER` меньше таймаута задания | выставить `3700`, `config:clear`, перезапустить воркер |
| В логе `Фоновая обработка очереди ИИ остановлена: нет прогресса` | ИИ-провайдер недоступен либо партия не разобралась | проверить `app:ai:health-check` и секции 8–9 диагностики |
| Растёт `failed_jobs` | задание падает с исключением | `php artisan queue:failed`, затем `queue:retry all` или `queue:flush` |

Очистить накопившиеся неактуальные задания (данные в них — только тип каталога, потерять нечего):

```bash
php artisan queue:clear database --queue=default
```

---

**См. также:** `docs/runbooks/production-git-pull.md` (деплой), `docs/runbooks/diag-builders-catalog.md` (диагностика пайплайна), `docs/ARTISAN_COMMANDS.md` (реестр команд).
