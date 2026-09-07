# Данные и архивные материалы

## Архивные системные промпты — кодом не читаются

Файлы `builder_ai_promt.txt`, `builder_ai_promt_fewshot.txt`, `builder_ai_promt_fewshot_2.txt`, `builder_ai_promt_binary_catalog.txt` и `builders_system prompt_ 21042026.docx` — снимки промптов апреля–мая 2026, оставленные для истории и сравнения формулировок. Приложение их не загружает.

Рабочие промпты живут в трёх местах:

- `api_channels.ai_promt` — промпт конкретного источника;
- таблица `admin_system_prompt_presets` и раздел админки «Системный промпт» — пресеты по областям;
- `config/builder_ai_pipeline.php` — `pass1_prompt` и `pass2_default_prompt` двухпроходного пайплайна строителей, с переопределением через `configurations` (`BuilderAiPipelineRuntimeConfig`).

Менять поведение ИИ правкой файлов в этом каталоге бессмысленно — нужно править пресет в админке или конфиг.

## Справочные данные

| Файл | Что это | Статус |
|------|---------|--------|
| `builder_specialities_updated.xlsx` | Источник справочника специализаций строителей (лист «Специализации»). Из него генерируется `app/Data/builder_speciality_dictionary.json` командой `python scripts/build_builder_speciality_json.py` | Актуален, путь прописан в скрипте |
| `Radarum_17.04_specialisation_list.csv` | Первая версия списка специализаций (апрель 2026), до перехода на xlsx | Архив |
| `vk_construction_groups.txt` | Список VK-сообществ строителей с регионами; источник — `VkConstructionGroupsChannelSeeder` | Справочно, может расходиться с БД при ручном добавлении каналов |
