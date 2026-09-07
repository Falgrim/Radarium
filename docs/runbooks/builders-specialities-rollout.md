# Выкатка справочника специализаций builders (итерация 1)

## Перед миграцией

1. Снять резервную копию таблицы связей (рекомендуется):
   - `builder_specialities`
   - при необходимости — `dictionary_specialities` (только строки с `api_data_type_id = 2`).

## Миграции

Выполнить по порядку (имена файлов):

1. `2026_04_21_100000_add_parent_id_to_dictionary_specialities_table.php` — колонка `parent_id`.
2. `2026_04_21_100001_reseed_builder_dictionary_specialities.php` — пересид только справочника Builder + очистка `builder_specialities` по старым id.
3. `2026_04_21_100002_append_builder_prompt_specialities_placeholder.php` — добавление блока с `{{SPECIALITIES_LIST}}` в `api_channels.ai_promt` и в пресеты `admin_system_prompt_presets` (scope `builder`), если плейсхолдера ещё нет.
4. `2026_04_22_100000_reseed_builder_dictionary_v2_xlsx.php` — **новая версия** справочника по `docs/reference/builder_specialities_updated.xlsx` (лист «Специализации»); исходник с группой + специализацией и `key_words` в `app/Data/builder_speciality_dictionary.json` (генерация: `python scripts/build_builder_speciality_json.py`). Повторно очищает `builder_specialities` по старым id справочника.

После п.4 на **PROD** обязателен пересчёт связей (см. ниже).

```bash
php artisan migrate
```

## Пересчёт специализаций у существующих записей

```bash
php artisan app:builders:rematch_specialities --dry-run
php artisan app:builders:rematch_specialities
```

Опции:

- `--dry-run` — только вывод diff, без записи.
- `--chunk=500` — размер чанка `lazyById`.
- `--only-empty` — только builders без строк в `builder_specialities`.
- `--builder=123` — один id.

## Проверки

- Страница `/builders`: мультиселект «Специализация» с `<optgroup>`.
- MoonShine → системный промпт строительства: подсказка про `{{SPECIALITIES_LIST}}`.
- Лог `ai_debug` после `app:ai_parse:builder`: поля `speciality_match_log`, `text_matched`, `ai_matched`.

## Откат

Откат миграций данных справочника не восстанавливает старые 27 профессий — нужен бэкап БД.
