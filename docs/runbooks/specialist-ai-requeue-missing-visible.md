# Повторная постановка в очередь ИИ: проектировщики без видимого сообщения в каталоге

Команда: `php artisan app:ai_parse:requeue-specialist-missing-visible-posts`

## Назначение

Для авторов, которые попадают в публичный каталог «Проектирование» (как в `PublicSpecialistCatalogScope::publicCatalogAuthorsQuery()`), но у которых **нет** активного specialist-поста со статусом **Complete** и непустым `post`, команда находит связанные посты каналов типа «Проектировщик» и ставит им `ai_parse_status = InQueue`.

Текст поля `api_channel_posts.post` **не меняется**.

## Статусы по умолчанию

В очередь попадают только посты со статусами **Error**, **Empty**, **DontMatch** (не **Complete**, не **InQueue**).

Расширить: `--status=*` (все кроме Complete и InQueue) или `--status=duplicate` и т.д.

## Безопасность

- Обязателен **либо** `--dry-run`, **либо** `--apply` (оба или ни одного — ошибка).
- Перед обновлением при `--apply` создаётся JSON-снимок в `storage/app/ai-reprocess/` (или `--backup-dir=`), затем обновляются только статусы постов.
- Сама ИИ-обработка **не** запускается: после `--apply` выполняйте `php artisan app:ai_parse:specialist` (до 100 постов за запуск; при необходимости несколько раз или по cron).

## Рекомендуемый порядок на проде

1. Задеплоить код (см. `docs/runbooks/production-git-pull.md`).
2. Проверка без записи:

   ```bash
   php artisan app:ai_parse:requeue-specialist-missing-visible-posts --dry-run
   ```

3. Пробная партия:

   ```bash
   php artisan app:ai_parse:requeue-specialist-missing-visible-posts --apply --limit=50
   php artisan app:ai_parse:specialist
   ```

4. Убедиться, что у выбранных авторов в каталоге появилось «Последнее сообщение».
5. Остальное — партиями (`--limit=200` и т.д.) или без лимита после успешной проверки.

## Проверка результата

Повторить `--dry-run` и сравнить число авторов без видимого Complete-сообщения.

## Риски

- `app:ai_parse:specialist` при успехе **пересоздаёт** записи `specialists` для поста — поэтому важен JSON-снимок до постановки в очередь.
- Повторный прогон ИИ может снова дать `Error` / `DontMatch` — это нормально для YandexGPT one-pass без локальных эвристик фазы 2.

См. также: [`specialist-ops-phase1.md`](specialist-ops-phase1.md).
