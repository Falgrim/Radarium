# Индекс файлов Radarium

Справочник путей. Смысл фич и порядок чтения — в `project_map.md`. Этот файл открывать по заголовку каталога, не целиком.

В индекс не входят `vendor/`, `node_modules/`, `.git/`, `storage/logs/`, `storage/framework/`, `storage/app/` (сессии Madeline и кэш), `public/build/`, `public/vendor/`, дампы `backup.sql` и `backup_before_restore.sql`. Имя миграции совпадает с её действием: полный список ниже, тела не читать, пока задача не про эту миграцию.

## Корень

| Файл | Роль |
|---|---|
| `project_map.md` | карта фич и ролей, читать первым |
| `index.MD` | этот указатель путей |
| `README.md` | локальный запуск `npm run dev` и `php artisan serve` |
| `composer.json` / `composer.lock` | PHP-зависимости |
| `package.json` / `package-lock.json` | фронт (Vite, Tailwind) |
| `vite.config.js`, `tailwind.config.js`, `postcss.config.js` | сборка CSS/JS |
| `phpunit.xml` | тесты |
| `.env.example` | образец окружения. `OLLAMA_MODEL=qwen3.5:9b`. `OLLAMA_HOST_HEADER` не задавать |
| `gitupdate.sh` | скрипт обновления на сервере |
| `crontest` | черновик cron, не источник расписания |
| `BUILDERS_ANALYSIS.md` | разовый разбор строителей, не runtime |
| `ТЗ_Замена_YandexGPT_на_E5_Base_Multilingual.md` / `.html` | отложенное ТЗ E5 |
| `update_moonshine_filters.py`, `tmp_*.php` | разовые скрипты, не часть приложения |

Каталоги IDE: `.cursor/`, `.vscode/`, `.superpowers/`. `public/.well-known/` — служебное для хостинга.

## `app/` — код приложения

### `app/Console/Commands/` — artisan

Сбор: `ParseTelegramBuilderChats`, `ParseTelegramSpecialistChats`, `ParseTelegramCompanyChats`, `ParseVkBuilderChats`, `ParseVkSpecialistChats`, `ParseVkCompanyChats`, `AuthTelegram`, `TelegramProfilesPhoto`.

Разбор ИИ: `AiBuilderPosts`, `AiSpecialistPosts`, `AiCompanyPosts`, `AiProviderHealthCheck` (`app:ai:health-check`), `AiYandexHealthCheck`, `SpecialityPosts`.

Очередь: `AiRequeuePosts`, `AiRequeueBuilderMissingVisiblePosts`, `AiRequeueSpecialistMissingVisiblePosts`, `AiArchiveStaleQueue`.

Каталог и словарь: `DiagnoseBuildersCatalog`, `CatalogPurgeStaleCards`, `NormalizePublicCatalogBuilderRegions`, `NormalizeStoredRegions`, `SyncChannelRegionToPosts`, `DisableActiveBuildersHiringTextCommand`, `RedirectNonFieldBuildersToSpecialistsCommand`, `EnableSpecialistChannelsCommand`, `LintBuilderSpecialityDictionary`, `BuilderSpecialityKeywordCandidates`, `RematchBuilderSpecialities`, `RebuildAuthorSpecialities`, `SyncCatalogAuthorSpecialities`, `BackfillBuilderSpecialityEvidence`, `ExportAuthorSpecialitiesGoldset`.

Прочее: `CheckUserTariffs`, `PaymentStatusCheck`, `SendMessageToTelegram`, `SetLastPostDateToUsers`, `SyncUsersWithTubus`. Общий трейт опции даты: `app/Console/Concerns/ParsesDateOption.php`. Реестр флагов и рисков: `docs/ARTISAN_COMMANDS.md`.

### `app/Services/` — поведение

| Файл | Роль |
|---|---|
| `ReadTelegramChats.php` | чтение чатов Telegram (MadelineProto) |
| `MadelineConnectionConfigurator.php` | параметры соединения Madeline |
| `MadelineSessionIpcCleaner.php` | сброс зависшей IPC-сессии |
| `ApiTelegram.php` | обёртка Telegram API |
| `SendMessageTelegram.php` | исходящие сообщения |
| `ReadVkGroups.php` | чтение групп VK |
| `VkApiClient.php` | HTTP-клиент VK |
| `ApiAIOllama.php` | локальная модель, `think=false`, Host для `79.175.45.41` |
| `ApiAIYandex.php` | YandexGPT |
| `ApiAIE5.php` | заготовка E5, не прод |
| `BuilderAiProviderFactory.php` | выбор Ollama или Yandex по каналу |
| `BuilderAiPipelineRuntimeConfig.php` | Pass 1/2 и настройки из config + БД |
| `BuilderAiPromptInjector.php` | подстановка `{{SPECIALITIES_LIST}}` |
| `BuilderOllamaParsePrompt.php` | текст Pass 2 для Qwen 3.5 |
| `BuilderServiceOfferClassifier.php` | Pass 1, бинарный тип |
| `BuilderVacancyGigHeuristic.php` | подработка / вакансия до или после модели |
| `AiModelJsonReplyDecoder.php` | JSON из ответа модели |
| `AiModelValueNormalizer.php` | типы полей под контракт |
| `AiParsePostExceptionHandler.php` | отказ модели и недоступность провайдера |
| `AiProviderHealthAlertService.php` | алерт, если health-check упал |
| `AiReprocessService.php` | повторный разбор поста |
| `BuilderUpsertService.php` | создание/обновление карточки строителя |
| `BuilderNormalizer.php` | нормализация полей карточки |
| `CatalogPublicationGate.php` | можно ли показать карточку |
| `CatalogPublicationBuilderNonServiceSignals.php` | сигналы «это не услуга» |
| `BuilderNonFieldSpecialistRedirect.php` | не-полевые строители → специалисты |
| `BuilderSpecialityMatcher.php` | пост → специальности словаря |
| `BuilderSpecialitySyncService.php` | синхронизация связей специальностей |
| `BuilderPostSpecialityEvidenceWriter.php` | улики «почему эта специальность» |
| `AuthorSpecialityProfiler.php` | профиль автора по уликам |
| `AuthorSpecialitySelection.php` | какие специальности показывать |
| `AuthorCatalogSpecialitiesSync.php` | профиль → каталог |
| `SpecialistPostImporter.php` | пост → карточка специалиста |
| `RussianRegionNormalizer.php` | канонические названия регионов |
| `Dictionary.php` | доступ к словарю |
| `Tariff.php` | тариф, триал, доступ к контактам |
| `ModerationAlertService.php` | жалобы |
| `AiSystemPromptAdminService.php` | пресеты промптов в админке |
| `ApiTubus.php` | синхронизация пользователей с Tubus |
| `Admin/ActiveAuthorsReportService.php` | отчёт активных авторов |
| `Admin/ModerationAuthorPostCatalogService.php` | автор поста в модерации |

### `app/Models/`

Каналы и посты: `ApiChannel`, `ApiChannelPost`, `ApiPostUser`, `ApiAi`. Карточки: `Builder`, `Specialist`, `CompanyJob`. Отзывы: `Review`, `ReviewCustomField`, `BuilderReview`, `BuilderReviewCustomField`, `CompanyJobReview`, `CompanyJobReviewCustomField`. Специальности: `DictionarySpeciality`, `BuilderSpeciality`, `SpecialistSpeciality`, `CompanyJobSpeciality`, `BuilderPostSpecialityEvidence`, `AuthorSpecialityScore`. Деньги и доступ: `Payment`, `PaymentTariff`, `UserTariff`, `UserOpenContact`. Прочее: `User`, `UserRole`, `Configuration`, `ModerationAlert`, `MailingMessage`, `MailingMessageLog`, `AdminSystemPromptPreset`.

### `app/Enum/`

Источник ИИ: `ApiAiSourceEnum` (`ollama_qwen`, `yandexgtp4`). Тип строителя: `BuilderTypeEnum`. Статусы постов и каналов: `ApiChannelPostStatusEnum`, `ApiPostAiStatusEnum`, `ApiChannelStatusEnum`, `ApiChannelSourceEnum`, `ApiAiStatusEnum`, `ApiDataTypeEnum`. Оплаты: `PaymentStatusEnum`, `PaymentTariffStatusEnum`, `PaymentServicePayEnum`, `UserTariffStatusEnum`. Модерация: `ModerationAlertStatusEnum`, `ModerationAlertSystemEnum`, `ModerationAlertTableNameEnum`. Отзывы: `ReviewStatusEnum`, `ReviewCanEditEnum`. Рассылки: `MailingMessageStatusEnum`, `MailingMessageLogStatusEnum`, `ApiPostUserMailingStatusEnum`. Промпты админки: `AdminSystemPromptScope`. Прочее: `CompanyJobStatusEnum`, `DictionaryEnum`, `ActiveAuthorsReportTypeEnum`.

### `app/Http/`

Сайт: `Controllers/IndexController.php`, `CatalogController.php`, `BuilderController.php`, `TariffController.php`, `ProfileController.php`, `SubscribeController.php`, `ModerationAlertController.php`, `ConfigurationController.php`. Админ-отчёт: `Controllers/Admin/ActiveAuthorsReportActionController.php`. API: `Controllers/Api/ApiCatalogController.php`. Auth (Breeze): `Controllers/Auth/*`. Запросы: `Requests/Auth/LoginRequest.php`, `ProfileUpdateRequest.php`, `UserRequest.php`, `ModerationAlertPostRequest.php`.

### `app/MoonShine/`

Ресурсы `Resources/*Resource.php` — CRUD моделей в админке. Страницы: `Pages/Dashboard.php`, `Configuration.php`, `SystemPromptPage.php`, `BuilderSystemPromptPage.php`, `SpecialistSystemPromptPage.php`, `ActiveAuthorsReportPage.php`, `Pages/ApiPostUser/*`. Меню: `MoonShineLayout.php`. Провайдер: `app/Providers/MoonShineServiceProvider.php`. Подсказка по Ollama: `Resources/ApiAiResource.php`.

### `app/Support/`

Каталог: `PublicBuilderCatalogScope.php`, `PublicSpecialistCatalogScope.php`, `CatalogLastMessage.php`, `CatalogRegionFilter.php`, `CatalogRegionOptions.php`, `CatalogRegionBuckets.php`, `CatalogBuilderRegionDisplay.php`. Ссылки: `TelegramPostUrl.php`. Отчёт: `Admin/ActiveAuthorsReportRow.php`.

### Остальное в `app/`

| Путь | Роль |
|---|---|
| `Data/builder_speciality_dictionary.json` | словарь специальностей строителей |
| `Data/BuilderSpecialityDictionaryData.php` | доступ к словарю из PHP |
| `Data/russian_cities_100k.json` | города для географии |
| `Jobs/ProcessPendingAiPosts.php` | очередь разбора |
| `Observers/*.php` | побочные эффекты save: канал, строитель, специалист, компания, оплата, тариф, рассылка, модерация, открытие контакта |
| `Policies/*.php` | по одному policy на ресурс MoonShine, имя = модель |
| `Repositories/` | `ConfigurationsRepository`, `DictionarySpecialityRepository`, `SpecialistsRepository`, `CompanyJobsRepository`, `UserRolesRepository` |
| `Infrastructures/Repository/Repository.php`, `Facades/Repositories.php` | база репозиториев |
| `Configurations/Configurations.php`, `ConfigurationsManager.php` | ключи настроек в БД |
| `Casts/LenientJsonArray.php` | JSON-каст, терпимый к битому значению |
| `Exceptions/AiContentRefusalException.php`, `AiProviderUnavailableException.php` | ошибки ИИ |
| `Providers/AppServiceProvider.php` | регистрация сервисов приложения |
| `View/Components/AppLayout.php` | layout Blade |

## `routes/`

| Файл | Роль |
|---|---|
| `web.php` | `/` лендинг, каталоги builders/specialists/companyjobs, тариф, профиль, отчёт авторов под префиксом MoonShine |
| `auth.php` | login, register, reset, verify |
| `api.php` | API каталога |
| `console.php` | только `inspire`, расписание продукта не здесь |

## `config/` — продуктовые

`services.php` (Ollama, Yandex), `builder_ai_pipeline.php` (Pass 1 и общий Pass 2), `catalog_publication_gate.php`, `author_speciality_profile.php`, `payment.php`, `vk.php`, `regions.php`, `russian_geography.php`, `catalog_region_filter.php`, `moonshine.php`.

Каркас Laravel, открывать только под свою задачу: `app.php`, `auth.php`, `cache.php`, `database.php`, `filesystems.php`, `logging.php`, `mail.php`, `queue.php`, `session.php`.

## `database/`

Сиды: `seeders/DatabaseSeeder.php`, `ConfigurationSeeder.php`, `TelegramBuilderChannelsSeeder.php` (title сервиса `qwen3.5:9b`), `VkConstructionGroupsChannelSeeder.php`, `ReviewSeeder.php`. Фабрика: `factories/UserFactory.php`.

Миграции (имя = действие). Актуальные для ИИ и каталога, не история колонок 2024–2025:

- `2026_03_12_000000_add_ai_provider_used_to_api_channel_posts_table.php`
- `2026_03_26_100000_add_builder_ai_fields_to_builders_table.php`
- `2026_03_26_100002_update_builder_channels_ai_promt.php`
- `2026_04_06_000001_create_admin_system_prompts_table.php`
- `2026_04_06_120000_create_admin_system_prompt_presets_table.php`
- `2026_04_21_100000_add_parent_id_to_dictionary_specialities_table.php` и соседние `reseed` / `append_builder_prompt_*` / `add_source_to_builder_specialities`
- `2026_05_06_090000_add_builder_pipeline_runtime_settings.php`
- `2026_10_13_100000_create_builder_post_speciality_evidence_table.php`
- `2026_10_13_100001_create_author_speciality_scores_table.php`
- `2026_10_13_100002_add_weak_negative_keywords_to_dictionary_specialities.php` и `100003_sync_builder_dictionary_weak_keywords`
- `2026_10_20_100000_sync_builder_dictionary_keywords_v3.php`, `2026_10_20_100000_resync_builder_dictionary_keywords_v3.php`
- `2026_10_21_120000_switch_ollama_model_to_qwen35.php`
- `2026_10_21_160000_replace_builder_ollama_parse_prompt.php`
- `2026_10_21_180000_set_ollama_router_host_header.php`

Остальные файлы `database/migrations/` — создание таблиц users/channels/posts/builders/payments и точечные `add_column` / `change_column`. Искать по подстроке в имени, не читать пачку.

## `resources/`

| Путь | Роль |
|---|---|
| `views/pages/index.blade.php`, `tech.blade.php` | лендинг и техстраница. `index_old.blade.php` — старый |
| `views/catalog/` | списки и карточки builders, specialists, companyjobs, покупка тарифа |
| `views/components/search-builders.blade.php`, `search-specialists.blade.php`, `catalog-search-tabs.blade.php`, `author-*.blade.php`, `*-row.blade.php` | поиск и строки каталога |
| `views/tariff/buy.blade.php`, `profile/` | оплата и профиль |
| `views/moonshine/custom/system-prompt-*.blade.php` | редактор промптов |
| `views/moonshine/components/ai-provider-health-banner.blade.php` | баннер health-check |
| `views/moonshine/active-authors-report*.blade.php` | отчёт авторов |
| `views/layouts/`, `views/components/` (кнопки, модалки, header, footer) | каркас Breeze/лендинга |
| `views/auth/` | экраны входа |
| `css/`, `js/` | исходники Vite |

## `tests/`

См. таблицу «Тесты рядом с фичей» в `project_map.md`. Каталоги: `tests/Unit/`, `tests/Unit/Services/`, `tests/Unit/Console/`, `tests/Unit/Models/`, `tests/Unit/Support/`, `tests/Feature/`, `tests/Feature/Auth/`, `tests/Feature/Console/`. База: `tests/TestCase.php`.

## `docs/`

Читать по одной строке из `project_map.md`, не каталог.

| Файл | Когда открывать |
|---|---|
| `docs/README.md` | оглавление документов для людей |
| `docs/ARTISAN_COMMANDS.md` | флаги, расписание, риски команды |
| `docs/RADARIUM_OVERVIEW.md` | продукт, аудитория, деньги — не код |
| `docs/RADARIUM_TECHDOC.md` | архитектура. Искать раздел, не читать файл |
| `docs/CHANGE_LOG.md` | что уже сделано. Искать дату |
| `docs/runbooks/production-git-pull.md` | выкладка |
| `docs/runbooks/queue-worker.md` | воркер очереди |
| `docs/runbooks/madelineproto-vpn-routing.md` | Telegram через VPN |
| `docs/runbooks/telegram-connection-incident-2026-07.md` | разбор инцидента связи |
| `docs/runbooks/diag-builders-catalog.md` | диагностика каталога строителей |
| `docs/runbooks/builder-ai-requeue-missing-visible.md` | доразбор пропущенных постов строителей |
| `docs/runbooks/specialist-ai-requeue-missing-visible.md` | то же для специалистов |
| `docs/runbooks/specialist-ops-phase1.md`, `specialist-channels-enable.md` | включение специалистов |
| `docs/runbooks/builders-specialities-rollout.md` | выкладка специальностей |
| `docs/plans/*` | намерения и отложенное (Docker, E5, приоритеты, триал, устаревший гайд тестов) |
| `docs/reference/README.md` | архивы. Промпты оттуда приложение не грузит |
| `docs/Logs/` | локальные заметки с сервера, в git не коммитятся |

## `scripts/`

| Файл | Роль |
|---|---|
| `build_builder_speciality_json.py` | сборка JSON словаря специальностей |
| `extract_wiki_cities.php` | выгрузка городов |
| `verify_builder_signals.php`, `verify_non_field_signals.php` | проверка сигналов публикации |
| `patch-madelineproto-connection.php`, `patch-madelineproto-exitfailure.php` | патчи вендорной Madeline, не бизнес-логика |
| `diagnostics/check-telegram-madelineproto-prod.sh`, `run-telegram-vpn-diagnostics.sh`, `restore-telegram-cron.sh`, `sync-xray-keys-check.sh`, `check-vpn-vps.sh` | диагностика Telegram/VPN на сервере |

## `bootstrap/`, `lang/`, `public/`

`bootstrap/app.php` — старт Laravel и расписание крона (`withSchedule`). `bootstrap/providers.php` — провайдеры. `lang/` — строки перевода. `public/index.php` — вход веб-сервера, `public/v2/` — статика лендинга. Исходники вёрстки править в `resources/`, не в `public/build/`.
