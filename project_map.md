# Карта проекта Radarium

Точка входа для агента. Читать этот файл целиком. `index.MD` открывать только за путём файла, которого нет ниже. Документацию из `docs/` открывать по ссылке из нужной фичи, не пакетом.

Продакшен: `progs.com`, путь `~/www/progs.com`, пользователь `www-root`, исходящий IP `77.222.58.189`. Стек: Laravel, MoonShine, Blade, MadelineProto (Telegram), VK API, Robokassa. Каталог авторов строительных услуг из Telegram и VK. Три контура карточек: строители, специалисты, компании.

## Что не читать

- `vendor/`, `node_modules/`, `storage/logs/`, `storage/framework/`, `public/build/`, `.git/`
- `backup.sql`, `backup_before_restore.sql`, `database/database.sqlite`
- `docs/CHANGE_LOG.md` целиком. Искать дату или имя миграции.
- `docs/RADARIUM_TECHDOC.md` целиком. Искать раздел по фиче из таблицы ниже.
- `docs/plans/` и `docs/reference/` — не runtime. Промпты в `docs/reference/` приложением не читаются.
- `ТЗ_Замена_YandexGPT_на_E5_Base_Multilingual.*` — отложенный план E5, код `ApiAIE5` не в проде.

## Фича → куда идти

| Задача | Сначала эти файлы | Документ, если не хватает |
|---|---|---|
| Сбор Telegram | `app/Services/ReadTelegramChats.php`, `app/Services/MadelineConnectionConfigurator.php`, команды `ParseTelegram*Chats` | `docs/runbooks/madelineproto-vpn-routing.md`, `docs/runbooks/telegram-connection-incident-2026-07.md` |
| Сбор VK | `app/Services/ReadVkGroups.php`, `app/Services/VkApiClient.php`, `config/vk.php`, команды `ParseVk*Chats` | раздел VK в `docs/RADARIUM_TECHDOC.md` |
| ИИ строителей, Pass 1/2 | `app/Console/Commands/AiBuilderPosts.php`, `BuilderServiceOfferClassifier`, `BuilderAiPipelineRuntimeConfig`, `BuilderAiProviderFactory`, `BuilderAiPromptInjector` | раздел пайплайна в `docs/RADARIUM_TECHDOC.md` |
| Промпт Qwen 3.5 (локальная модель) | `app/Services/BuilderOllamaParsePrompt.php` — текст Pass 2. Куда пишется: миграция `2026_10_21_160000_replace_builder_ollama_parse_prompt.php` | не `docs/reference/builder_ai_promt*.txt` |
| Промпт Яндекса и общий fallback | `config/builder_ai_pipeline.php` ключ `pass2_default_prompt`. Пустой `api_channels.ai_promt` берёт его у всех провайдеров | не подменять текстом из `BuilderOllamaParsePrompt` |
| Подключение Ollama | `app/Services/ApiAIOllama.php`, `config/services.php` (`ollama`), `app/Console/Commands/AiProviderHealthCheck.php` | см. блок «Локальная модель» ниже |
| Подключение YandexGPT | `app/Services/ApiAIYandex.php`, `app/Console/Commands/AiYandexHealthCheck.php` | — |
| Карточка строителя из JSON | `BuilderUpsertService`, `BuilderNormalizer`, `CatalogPublicationGate`, `CatalogPublicationBuilderNonServiceSignals`, `BuilderVacancyGigHeuristic` | — |
| Специалисты | `AiSpecialistPosts`, `SpecialistPostImporter`, модель `Specialist` | `docs/runbooks/specialist-ops-phase1.md`, `specialist-channels-enable.md`, `specialist-ai-requeue-missing-visible.md` |
| Компании / вакансии компаний | `AiCompanyPosts`, модель `CompanyJob`, `CompanyJobsRepository` | — |
| Специализации и профиль автора | `BuilderSpecialityMatcher`, `AuthorSpecialityProfiler`, `AuthorSpecialitySelection`, `AuthorCatalogSpecialitiesSync`, `BuilderPostSpecialityEvidenceWriter`, словарь `app/Data/builder_speciality_dictionary.json` | `docs/runbooks/builders-specialities-rollout.md` |
| Публичный каталог | `CatalogController`, `BuilderController`, `app/Support/Catalog*.php`, `app/Support/Public*CatalogScope.php`, views `resources/views/catalog/` | — |
| Регионы | `RussianRegionNormalizer`, `config/regions.php`, `config/russian_geography.php`, `config/catalog_region_filter.php`, `app/Data/russian_cities_100k.json` | — |
| Тариф, триал, Robokassa | `app/Services/Tariff.php`, `TariffController`, модели `Payment`, `PaymentTariff`, `UserTariff`, `config/payment.php`, команды `CheckUserTariffs`, `PaymentStatusCheck` | `docs/plans/TRIAL_TARIFF_EXPIRATION_PLAN.md` — дизайн, код уже в `Tariff.php` и `User` |
| Модерация | `ModerationAlertService`, `ModerationAlertController`, `ModerationAlertResource` | — |
| Админка MoonShine | `app/MoonShine/`, `app/Providers/MoonShineServiceProvider.php`, `config/moonshine.php` | раздел MoonShine в техдоке |
| Системные промпты в админке | `SystemPromptPage`, `BuilderSystemPromptPage`, `SpecialistSystemPromptPage`, `AiSystemPromptAdminService`, модель `AdminSystemPromptPreset` | страница показывает пресеты, не поле канала `ai_promt` |
| Отчёт активных авторов | `ActiveAuthorsReportPage`, `ActiveAuthorsReportService`, `ActiveAuthorsReportActionController` | — |
| Тест точности отбора строителей | `ParsingAccuracyTestPage`, `ParsingAccuracyTestService`, `BuilderTargetDecision`, команда `ParsingAccuracyTest` | админка «Тесты парсинга» → «Строительство». Пишет только `parsing_test_runs` / `parsing_test_messages`, посты и каталог не меняет |
| Пользователи, Tubus, роли | `User`, `UserRole`, `ApiTubus`, `SyncUsersWithTubus`, Breeze-контроллеры `app/Http/Controllers/Auth/` | — |
| Рассылки в Telegram | `MailingMessage`, `MailingMessageObserver`, `SendMessageTelegram`, `SendMessageToTelegram` | — |
| Очередь и повторный разбор | `app/Jobs/ProcessPendingAiPosts.php`, `AiReprocessService`, `AiRequeuePosts`, `AiArchiveStaleQueue`, `AiRequeue*MissingVisiblePosts` | `docs/runbooks/queue-worker.md`, `builder-ai-requeue-missing-visible.md` |
| Деплой | `gitupdate.sh`, `docs/runbooks/production-git-pull.md` | — |
| Команды artisan | реестр с рисками: `docs/ARTISAN_COMMANDS.md`. Список классов: `app/Console/Commands/` | — |

Маршруты сайта: `routes/web.php` (каталог, тариф, профиль, отчёт авторов), `routes/auth.php`, `routes/api.php` (`ApiCatalogController`). Расписание крона — `bootstrap/app.php` (`withSchedule`) и реестр в `docs/ARTISAN_COMMANDS.md`. `routes/console.php` содержит только `inspire`. `app/Console/Kernel.php` нет.

## Роли runtime

| Роль | Файлы |
|---|---|
| Источник постов | `ApiChannel` (Telegram/VK, тип строитель/специалист/компания, регион, `ai_promt`, ссылка на `ApiAi`) |
| Сырой пост и статус ИИ | `ApiChannelPost`, enum `ApiChannelPostStatusEnum`, `ApiPostAiStatusEnum` |
| Автор в каталоге | `ApiPostUser` |
| Карточки | `Builder`, `Specialist`, `CompanyJob` плюс отзывы `*Review` |
| Провайдер ИИ | `ApiAi`: `api_source` `ollama_qwen` или `yandexgtp4`, модель и host в `options` / `config/services.php`. Enum `ApiAiSourceEnum`, значение `ollama_qwen` не менять |
| Словарь специальностей | `DictionarySpeciality`, связь `BuilderSpeciality` (`source`), улики `BuilderPostSpecialityEvidence`, счёт `AuthorSpecialityScore` |
| Деньги | `Payment`, `PaymentTariff`, `UserTariff`, `UserOpenContact` |
| Настройки в БД | `Configuration` через `Configurations` / `ConfigurationsManager`. Runtime пайплайна строителей дублируется ключами из миграции `2026_05_06_090000_add_builder_pipeline_runtime_settings.php` |
| Админ-пресеты промптов | `admin_system_prompt_presets`, `api_source`: `ollama_qwen`, `yandexgtp4` или `all`. Пресет `all` («Все ИИ обработчики») перезаписывает источники всех провайдеров — не класть в него текст Qwen |

Политики `app/Policies/*Policy.php` зеркалят модели MoonShine один к одному. Обсерверы `app/Observers/` — побочные эффекты при сохранении канала, карточки, оплаты, тарифа, рассылки, модерации.

## Локальная модель (Qwen 3.5)

- Тег Ollama: `qwen3.5:9b`. В запросе чата `'think' => false`. Не писать `/no_think` и XML-роли в промпт.
- Хост с точки зрения приложения: `http://79.175.45.41:11434` (NAT роутера на `192.168.88.127:11434`). Ollama на 127.0.0.1 отвечает 403 на чужой Host. `ApiAIOllama::resolveHostHeader` для этого IP подставляет `Host: 192.168.88.127:11434`. В `.env` строку `OLLAMA_HOST_HEADER` не добавлять. Миграция `2026_10_21_180000_set_ollama_router_host_header.php` пишет то же значение в `api_ais.options.host_header`.
- Проверка связи: `php artisan app:ai:health-check` (GET `/api/tags`). Успех: `OK: qwen3.5:9b (http://79.175.45.41:11434)`.
- Новый Pass 2 только у каналов строителей с непустым `ai_promt` и `api_source=ollama_qwen`, плюс пресет scope builder с тем же `api_source` (имя «Qwen 3.5», если пресета не было). Яндекс, пресет `all` и `builder_pass2_default_prompt` остаются со старым текстом.
- В каталог попадает только тип «предложение услуги» (`BuilderTypeEnum::Service`). Сомнение услуга/вакансия → вакансия. Команда `app:ai_parse:builder` берёт 100 новых InQueue и пересоздаёт карточки (ручные правки теряются). Лог: `storage/logs/ai_debug.log`.

## Конфиги с поведением продукта

| Файл | Зачем |
|---|---|
| `config/services.php` | Ollama host/model, Yandex |
| `config/builder_ai_pipeline.php` | Pass 1, общий Pass 2 fallback, флаги пайплайна |
| `config/catalog_publication_gate.php` | что публиковать в каталог |
| `config/author_speciality_profile.php` | пороги профиля специальностей |
| `config/payment.php` | Robokassa |
| `config/vk.php` | VK |
| `config/regions.php`, `config/russian_geography.php`, `config/catalog_region_filter.php` | география и фильтр каталога |
| `config/moonshine.php` | префикс админки |

Остальные файлы `config/` — каркас Laravel (`app`, `auth`, `cache`, `database`, `filesystems`, `logging`, `mail`, `queue`, `session`).

## Опасные команды

Полный реестр и расписание: `docs/ARTISAN_COMMANDS.md`.

- `app:ai_parse:builder` / specialist / company — живой разбор очереди, без dry-run.
- `app:ai:requeue*` и `AiArchiveStaleQueue` — меняют статусы постов.
- `CatalogPurgeStaleCards`, `DisableActiveBuildersHiringTextCommand`, `RedirectNonFieldBuildersToSpecialistsCommand` — правят каталог.
- `RematchBuilderSpecialities`, `RebuildAuthorSpecialities`, `SyncCatalogAuthorSpecialities` — пересчёт связей.

## Тесты рядом с фичей

| Фича | Тест |
|---|---|
| Ollama, Host, think=false | `tests/Unit/ApiAIOllamaTest.php` |
| Контракт промпта Qwen и что fallback ему не равен | `tests/Unit/BuilderOllamaParsePromptTest.php` |
| Pass 1, гиг, специальности, публикация | `tests/Unit/Services/BuilderServiceOfferClassifierTest.php`, `BuilderVacancyGigHeuristicTest.php`, `BuilderSpecialityMatcherTest.php`, `CatalogPublicationGateTest.php`, `CatalogPublicationBuilderNonServiceSignalsTest.php` |
| Отбор целевого типа после ответа модели | `tests/Unit/Services/BuilderTargetDecisionTest.php` — без boot Laravel, этот CLI без `pdo_sqlite` его выполняет |
| JSON модели | `AiModelJsonReplyDecoderTest.php`, `AiModelValueNormalizerTest.php` |
| Очередь и requeue | `tests/Feature/Console/AiRequeue*.php`, `AiArchiveStaleQueueTest.php` |
| Тариф и триал | `TariffTest.php`, `TariffTrialAccessTest.php`, `UserTrialTest.php`, `TrialExpiredNoticeTest.php` |
| Регионы каталога | `CatalogRegionFilterTest.php`, `CatalogBuilderRegionDisplayTest.php`, `RussianRegionNormalizerTest.php` |
| Telegram/VK чтение | `tests/Feature/ReadTelegramChatsTest.php`, `ReadVkGroupsTest.php` |

Прогон точечно: `php artisan test --filter=ИмяТеста`. Локально нет `.env`, CLI PHP без `pdo_sqlite` — полный boot приложения падает, unit-тесты без БД проходят.

## Куда писать изменение

| Меняется | Куда |
|---|---|
| Поведение кода | `app/`, тест в `tests/Unit` или `tests/Feature` |
| Текст Pass 2 только для Qwen | константа `BuilderOllamaParsePrompt::TEXT` и новая миграция по образцу `2026_10_21_160000` (каналы `ollama_qwen` с непустым промптом и пресет `ollama_qwen`). Не трогать `pass2_default_prompt` |
| Общий fallback и Яндекс | `config/builder_ai_pipeline.php` и отдельное решение по каналам `yandexgtp4` |
| Схема БД | новая миграция в `database/migrations/`, имя `YYYY_MM_DD_HHMMSS_глагол.php` |
| Операция на проде | `docs/runbooks/`, команда в `docs/ARTISAN_COMMANDS.md` |
| Факт изменения для людей | короткая запись в `docs/CHANGE_LOG.md`, без пересказа всего техдока |
