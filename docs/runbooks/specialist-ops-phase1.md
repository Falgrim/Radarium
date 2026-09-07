# Фаза 1: операционный слой проектировщиков

Runbook для включения каналов, smoke-парсинга и ops-команд (YandexGPT, без two-pass/эвристик).

См. также: [`specialist-channels-enable.md`](specialist-channels-enable.md), [`specialist-ai-requeue-missing-visible.md`](specialist-ai-requeue-missing-visible.md), [`telegram-connection-incident-2026-07.md`](telegram-connection-incident-2026-07.md).

## Порядок на production

1. Задеплоить код (см. [`production-git-pull.md`](production-git-pull.md)).

2. Инвентаризация отключённых каналов:

   ```bash
   php artisan app:channels:enable-specialists --dry-run
   ```

   Сверить список, предупреждения «не YandexGPT» / «ИИ отключён». Провайдер автоматически не меняется.

3. Включить каналы:

   ```bash
   php artisan app:channels:enable-specialists --apply
   ```

   Точечно: `--id=12 --id=34`. Только Telegram: `--source=telegram`.

4. Проверить Madeline-сессию (при ошибках IPC/VPN — runbook TG). При необходимости:

   ```bash
   php artisan app:tg_auth --api-id=... --qr
   ```

5. Smoke-парсинг:

   ```bash
   php artisan app:tg_parse:specialist
   php artisan app:vk_parse:specialist
   ```

   Ожидание: нет массовых ошибок session/IPC; растут `api_channel_posts` для каналов `is_company = Specialist`.

6. ИИ (YandexGPT по `api_channels.api_ai_id`):

   ```bash
   php artisan app:ai_parse:specialist
   ```

7. При «дырах» в каталоге (автор без видимого Complete-сообщения):

   ```bash
   php artisan app:ai_parse:requeue-specialist-missing-visible-posts --dry-run
   php artisan app:ai_parse:requeue-specialist-missing-visible-posts --apply --limit=50
   php artisan app:ai_parse:specialist
   ```

8. Массовый reset очереди за период (осторожно — удаляет связанные `specialists`):

   ```bash
   php artisan app:ai_parse:requeue --type=specialist --status=all --from=2026-07-01 --to=2026-08-07 --dry-run
   php artisan app:ai_parse:requeue --type=specialist --status=all --from=2026-07-01 --to=2026-08-07 --provider=yandexgtp4
   ```

   Обработка стартует сразу в фоне (нужен запущенный `queue:work`); для прежнего поведения добавьте `--no-dispatch`.

## Критерии готовности фазы 1

- Disabled specialist-каналы включены (или оставлены выключенными осознанно после dry-run)
- `tg_parse:specialist` без ошибок shared Madeline
- AI остаётся на YandexGPT (исключения только в отчёте)
- Доступны `app:ai_parse:requeue` и `requeue-specialist-missing-visible-posts`
- **Не** переносились two-pass Ollama и hiring-эвристики builders

## Фаза 2 (позже)

Локальная фильтрация (Ollama two-pass, эвристики) — отдельный план после подтверждения фазы 1.
