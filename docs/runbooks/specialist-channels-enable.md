# Включение каналов проектировщиков

Команда: `php artisan app:channels:enable-specialists`

## Назначение

Показать и при необходимости включить (`status = Active`) отключённые записи `api_channels` с `is_company = Specialist (0)`.

Провайдер ИИ (`api_ai_id` / `api_source`) **не меняется**. В отчёте помечаются каналы без YandexGPT или с отключённым ИИ.

## Режимы

Обязателен **либо** `--dry-run`, **либо** `--apply`.

```bash
# Список отключённых
php artisan app:channels:enable-specialists --dry-run

# Включить все отключённые specialist-каналы
php artisan app:channels:enable-specialists --apply

# Только Telegram / только выбранные id
php artisan app:channels:enable-specialists --dry-run --source=telegram
php artisan app:channels:enable-specialists --apply --id=12 --id=34

# В отчёт включить уже активные (apply всё равно трогает только Disabled)
php artisan app:channels:enable-specialists --dry-run --include-active
```

## После включения

1. `php artisan app:tg_parse:specialist` (и при необходимости `app:vk_parse:specialist`)
2. `php artisan app:ai_parse:specialist`

Полный чеклист: [`specialist-ops-phase1.md`](specialist-ops-phase1.md).
