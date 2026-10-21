# Выкатка справочника и профиля специализаций builders

## Итерация 1 (справочник, апрель 2026)

### Перед миграцией

1. Снять резервную копию таблицы связей (рекомендуется):
   - `builder_specialities`
   - при необходимости — `dictionary_specialities` (только строки с `api_data_type_id = 2`).

### Миграции

Выполнить по порядку (имена файлов):

1. `2026_04_21_100000_add_parent_id_to_dictionary_specialities_table.php` — колонка `parent_id`.
2. `2026_04_21_100001_reseed_builder_dictionary_specialities.php` — пересид только справочника Builder + очистка `builder_specialities` по старым id.
3. `2026_04_21_100002_append_builder_prompt_specialities_placeholder.php` — добавление блока с `{{SPECIALITIES_LIST}}` в `api_channels.ai_promt` и в пресеты `admin_system_prompt_presets` (scope `builder`), если плейсхолдера ещё нет.
4. `2026_04_22_100000_reseed_builder_dictionary_v2_xlsx.php` — **новая версия** справочника по `docs/reference/builder_specialities_updated.xlsx` (лист «Специализации»); исходник с группой + специализацией и `key_words` в `app/Data/builder_speciality_dictionary.json` (генерация: `python scripts/build_builder_speciality_json.py`). Повторно очищает `builder_specialities` по старым id справочника.

```bash
php artisan migrate
```

## Итерация 2 (профиль автора, октябрь 2026)

Цель: точная маркировка автора по **всему корпусу** целевых сообщений без новых LLM-вызовов.

### Миграции

1. `2026_10_13_100000_create_builder_post_speciality_evidence_table.php`
2. `2026_10_13_100001_create_author_speciality_scores_table.php`
3. `2026_10_13_100002_add_weak_negative_keywords_to_dictionary_specialities.php`
4. `2026_10_13_100003_sync_builder_dictionary_weak_keywords.php` — идемпотентное обновление ключей из JSON **без** смены id и без очистки pivot.

```bash
php artisan migrate
```

### Env

```env
AUTHOR_SPECIALITY_PROFILE_ENABLED=true
AUTHOR_SPECIALITY_PROFILE_SHADOW=true   # сначала shadow
AUTHOR_SPECIALITY_MATCHER_VERSION=v2
AUTHOR_SPECIALITY_MAX=3
```

### Порядок на PROD

1. Бэкап `builder_specialities` (и при желании `dictionary_specialities`).
2. `php artisan migrate`
3. Backfill evidence:

```bash
php artisan app:builders:backfill_speciality_evidence --dry-run
php artisan app:builders:backfill_speciality_evidence
```

4. Shadow-rebuild + CSV сравнения (названия «было → стало»; `--changed-only` — только изменившиеся, удобно для руководства):

```bash
php artisan app:builders:rebuild_author_specialities --dry-run --compare-csv=storage/app/author_spec_compare.csv --changed-only
php artisan app:builders:rebuild_author_specialities --compare-csv=storage/app/author_spec_compare.csv --changed-only
```

Колонки CSV: `old_speciality_titles`, `new_speciality_titles` (человекочитаемые), плюс id, `changed`, `top_scores` (title:score).

5. Золотой набор / диагностика обнулений и калибровка порогов в `config/author_speciality_profile.php`:

```bash
# Стратифицированная выборка для ручной разметки
php artisan app:builders:author_specialities_export --limit=200

# Все авторы, у которых специализации «стало пусто»: 1 строка = 1 сообщение
# (причина empty_reason, ответ ИИ, evidence матчера, полный текст)
php artisan app:builders:author_specialities_export --lost-only --per-post --out=storage/app/lost_specialities_posts.csv
```

6. Выключить shadow (`AUTHOR_SPECIALITY_PROFILE_SHADOW=false`), `config:clear`, затем:

```bash
php artisan app:builders:rebuild_author_specialities --dry-run
php artisan app:builders:rebuild_author_specialities
```

7. Линтер словаря (по желанию):

```bash
php artisan app:builders:specialities_lint_dictionary
```

### Пересчёт после правок словаря / логики матчера (v3+)

После деплоя правок матчера/порогов/JSON ключей:

```bash
# .env
AUTHOR_SPECIALITY_MATCHER_VERSION=v3
# затем
php artisan migrate
php artisan config:clear
php artisan app:builders:backfill_speciality_evidence --force
php artisan app:builders:rebuild_author_specialities --compare-csv=storage/app/author_spec_compare_v3.csv --changed-only
# контроль оставшихся пустых:
php artisan app:builders:author_specialities_export --lost-only --per-post --out=storage/app/lost_specialities_posts_v3.csv
```

Либо точечно:

```bash
php artisan app:builders:rematch_specialities --dry-run
php artisan app:builders:rematch_specialities
```

### Чистка проектировщиков/визуализаторов из builders

Перед или после пересчёта специализаций убрать из `/builders` авторов с текстом проектирования/визуализации/дизайна:

```bash
php artisan app:catalog:redirect-non-field-builders-to-specialists --dry-run
php artisan app:catalog:redirect-non-field-builders-to-specialists
```

Если автор уже в проектировщиках — все его builder-карточки удаляются; иначе создаётся Specialist и затем чистятся Builder.

### Чистка проектировщиков/визуализаторов из builders

Перед или после пересчёта специализаций убрать из `/builders` авторов с текстом проектирования/визуализации/дизайна:

```bash
php artisan app:catalog:redirect-non-field-builders-to-specialists --dry-run
php artisan app:catalog:redirect-non-field-builders-to-specialists
```

Если автор уже в проектировщиках — все его builder-карточки удаляются; иначе создаётся Specialist и затем чистятся Builder.

### Проверки

- Страница `/builders`: чипы специализаций — листья, порядок по релевантности (не по id).
- Фильтр по группе по-прежнему находит авторов с листовыми специализациями.
- Лог `ai_debug`: `[AuthorSpecialityProfiler] profileBuildersForUser`, evidence write в `AiBuilderPosts`.
- Таблицы: `builder_post_speciality_evidence`, `author_speciality_scores` (`is_selected=1`, `rank` 1..N).

### Откат

1. `AUTHOR_SPECIALITY_PROFILE_ENABLED=false` — вернётся legacy-склейка в `AuthorCatalogSpecialitiesSync`.
2. Pivot восстановить из бэкапа или `php artisan app:builders:rematch_specialities` на старой версии матчера (после отката кода).
3. Откат миграций данных справочника (итерация 1) не восстанавливает старые 27 профессий — нужен бэкап БД.
