# Radarium — журнал изменений

> Период: **с 08.04.2026** по состояние репозитория на **08.07.2026**.

---

## 2026-07-08

### Production / доступ к GitLab (progs.com)
- Задокументирован runbook обновления кода на production: `docs/production-git-pull.md`.
- Зафиксирован рабочий способ: **HTTPS** + **Deploy Token** (`read_repository`), username вида `gitlab+deploy-token-...`, сохранение через `git config --global credential.helper store`.
- Описаны типовые ошибки: SSH `Permission denied (publickey)`, `Fingerprint sha256 has already been taken`, 403 для classic PAT (нужен fine-grained с **Code: Download** или deploy token).
- В `DOC/RADARIUM_TECHDOC.md` добавлен §15 со ссылкой на runbook.

---

## 2026-06-10

### Telegram / MadelineProto — восстановление и эксплуатация
- **`app:tg_auth`**: опции `--api-id`, `--api-hash`, `--qr`, `--reset`; QR-вход и 2FA; `api_hash` из `api_channels` при явном `--api-id`.
- **`MadelineConnectionConfigurator::buildSettings()`** — единые настройки для парсинга и авторизации.
- **`SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED`** — временное отключение cron-рассылки `app:tg_chat:send_company`.
- Документация: §6.9 runbook в `DOC/RADARIUM_TECHDOC.md`, раздел «Восстановление сессии» в `docs/madelineproto-vpn-routing.md`.

---

## 2026-05-15

### AI / Builder pipeline
- Уточнены промпты Pass 1 и Pass 2 в `config/builder_ai_pipeline.php`: детализированы критерии «предложение услуги» vs «мусор».
- В `CatalogPublicationBuilderNonServiceSignals` добавлена причина отсечения для компактных заказов с ценой за единицу объёма; расширены unit-тесты.
- Добавлена эвристика «низкий лексический сигнал» (spam) в `CatalogPublicationBuilderNonServiceSignals`; `AiBuilderPosts` использует новые blocking reasons.
- Команда `app:catalog:disable-active-builders-hiring-text`: активные карточки строителей с текстом найма/не-услуги переводятся в **`InModeration`** (ранее — `Disabled`); поддержка `--dry-run`.

---

## 2026-05-14

### AI / фильтрация строителей
- Доработаны regex и промпты классификации в `CatalogPublicationBuilderNonServiceSignals` и `builder_ai_pipeline` (emoji-заголовки, фразы найма подрядчиков).
- `BuilderAiPipelineRuntimeConfig`: снят модификатор `final` для гибкости расширения.

### Специализации и админка
- `AuthorCatalogSpecialitiesSync`: расширено логирование синхронизации специализаций.
- `BuilderResource`: ужесточена валидация поля специализаций.

### Регионы
- Единая нормализация через `RussianRegionNormalizer::normalize` в командах и контроллерах.
- `CatalogRegionOptions`: выпадающий список регионов строится только из канонических имён, без мусорных значений (`null`, `undefined` и т.п.).

### Web / сессии
- Время жизни сессии: **30 минут** (`SESSION_LIFETIME=30`).
- Компоненты `session-flash-banner`, `session-idle-timeout` во всех layout-ах.
- Маршрут `GET /session/csrf` — обновление CSRF-токена без перезагрузки страницы; AJAX-формы обрабатывают HTTP 419.
- Редирект после входа и dashboard: **`catalog.builders`** вместо `catalog.specialists`.

### Telegram / MadelineProto
- Информационное логирование подключения в командах `ParseTelegram*`.
- Удалена логика IPC-reconnect; упрощено управление клиентом MadelineProto (release/finalize после чтения каналов).
- Убрана настройка `MPROTO_IPC_RECONNECT_ATTEMPTS` из `.env.example` и `config/services.php`.

---

## 2026-05-13

### Telegram / MadelineProto
- Команды `ParseTelegram*` переведены на **`readTelegramChannelsWithSharedSession`**: один экземпляр MadelineProto на группу каналов с одинаковым `api_id` (меньше IPC-гонок).
- Сессии: уникальные имена `session.madeline.{apiId}`; цикл chmod для нескольких session-файлов.
- Новый post-install патч: `scripts/patch-madelineproto-exitfailure.php` (обработка `Ipc/ExitFailure.php`).

### Специализации
- `BuilderSpecialityMatcher::resolve`: параметр **`maxSpecialityIds`** — ограничение числа специализаций на автора (по умолчанию **3** через `AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR`).
- `AiBuilderPosts`, `AiSpecialistPosts`, `RematchBuilderSpecialities`: синхронизация специализаций автора после импорта через `AuthorCatalogSpecialitiesSync`.

---

## 2026-05-12

### Фильтрация «не-услуг» для строителей
- Расширены эвристики в `CatalogPublicationBuilderNonServiceSignals`: явный найм заказчиком, предложения бригад, срочные заказы, нормализация текста.
- Усилена фильтрация постов строителей с маркерами найма персонала в AI-пайплайне.
- Новая команда **`app:catalog:disable-active-builders-hiring-text`** — массовое снятие с публикации активных карточек по эвристикам найма.

---

## 2026-05-07

### Эвристики вакансий / подработок
- `BuilderVacancyGigHeuristic` и `CatalogPublicationBuilderNonServiceSignals`: проверки на частичную занятость, жильё/инструменты, численность бригады, ссылки Telegram, колонны/туры/объёмы работ, условия оплаты.
- Unit-тесты для новых правил.

### Модерация
- Поле **`api_channel_post_id`** в `moderation_alerts` (миграция, модель, формы, модальные окна на сайте).
- `ModerationAlertPostRequest`: обработка пустого `api_channel_post_id`.
- Улучшено логирование ошибок в `ModerationAlertObserver`.

### Telegram
- `ReadTelegramChats::getHistoryWithCancelledRetries` — повторные попытки при `Amp\CancelledException`.
- Конфиг `madeline_proto`: `max_history_cancel_retries`.
- Расширено логирование ошибок чтения (тип исключения + id канала).

---

## 2026-05-06

### Документация
- Обновлён `DOC/RADARIUM_TECHDOC.md`: двухэтапный Builder AI pipeline (Ollama/Qwen), anti-hallucination, runtime-настройки через `configurations`.

### Каталог
- `PublicBuilderCatalogScope` / контроллеры: в выдаче строителей и специалистов учитываются только посты с **`ai_parse_status = Complete`**.

### Модерация (MoonShine)
- Новые статусы `ModerationAlertStatusEnum`: **`AiReprocessing`** («Повторная обработка»), **`RemovedFromCatalog`** («Убрано из каталога»).
- `ModerationAlertResource`: кнопки повторной ИИ-обработки и снятия с каталога; цветовая кодировка статусов.

### Админка
- `saveFilterState = true` для всех MoonShine-ресурсов.
- `ConfigurationResource`: кастомная высота textarea для длинных настроек.

---

## 2026-05-05

### CatalogPublicationGate
- Для домена Builder: эвристики hiring-текстов и коротких заказов без самопрезентации исполнителя; конфиг `catalog_publication_gate.php`; unit-тесты.

---

## 2026-05-04

### Документация
- Крупное обновление `RADARIUM_TECHDOC.md`: VK-импорт, архитектура, `CatalogPublicationGate`, репозитории настроек.

---

## 2026-04-30

### Публикация в каталог
- **`CatalogPublicationGate`**: решение об `Active` / `InModeration` после AI для builders и specialists.
- Env: `CATALOG_PUBLICATION_GATE_ENABLED`, `CATALOG_PUBLICATION_GATE_REQUIRE_SPECIALITY`.
- Лог-канал `catalog_publication_gate`.

### Модерация
- `ModerationAlertResource`: requeue поста в ИИ (`requeueAuthorPostForAi`), снятие с каталога без ИИ (`removeAuthorPostFromCatalog`).
- Фикс кнопок повторной обработки ИИ.

### AI / промпты
- Обновлены промпты для классификации вакансий/подработок.
- `AiBuilderPosts`: эвристика `BuilderVacancyGigHeuristic` — принудительная смена типа на «вакансия»; лог `builder_type_override`.

### MadelineProto
- `MadelineConnectionConfigurator`: прокси (SOCKS5/HTTP), IPv6, таймауты, file logger в `storage/logs/MadelineProto.log`.
- Убран `updateSettings` сразу после конструктора API (устранение IPC-гонок).
- Патч `patch-madelineproto-connection.php` в `composer.json` post-autoload-dump.
- Env: `MPROTO_IPV6_ENABLED`, `MPROTO_PROXY_*`, `MPROTO_CONNECTION_TIMEOUT`.

### Отчёты
- `ActiveAuthorsReportActionController`: корректная фильтрация статусов через `array_filter` с ключами.

---

## 2026-04-29

### MadelineProto
- Централизованная настройка подключения через `MadelineConnectionConfigurator` в `ReadTelegramChats`, `TelegramProfilesPhoto`, `SendMessageTelegram`.
- Параметры прокси в `.env.example`.

---

## 2026-04-25

### Отчёт «Уникальные авторы» (MoonShine)
- Новая страница **`ActiveAuthorsReportPage`** с фильтрами и пагинацией.
- Серия UI-фиксов: шапка отчёта, колонки, вертикальная пагинация, улучшение интерфейса.

### Прочее
- Фикс системного промпта для строительных сообщений.
- Команда/логика возврата постов авторам, потерявшим привязку сообщений.

---

## 2026-04-24

### Регионы и поиск
- Крупная переработка определения и поиска по регионам/городам (`RussianRegionNormalizer`, `config/regions.php`).
- Выпадающий список регионов: только реальные канонические значения; fix `null` в select.
- Fix неактуальных сообщений на экране поиска.

### Админка / модерация
- Кнопка изменения статуса ИИ для сообщений в модерации.
- Серия фиксов редактирования записей строительства в MoonShine.
- Внедрение обновлённого справочника специализаций (правки Анны).
- Правка выпадающего списка поиска по специализациям.

---

## 2026-04-21

### Справочник специализаций
- Переработан справочник специализаций для области «строительство» и механизм матчинга (`BuilderSpecialityMatcher`, миграции словаря).

---

## 2026-04-15

### Админка (MoonShine)
- Откат к базовому дизайну админ-панели после сбоя темы.
- Коррекция ширины панели фильтров.

---

## 2026-04-14

### Админка / пользователи
- Редакция фильтров MoonShine.
- Поле **последний логин пользователя** в `UserResource`.

---

## 2026-04-13

### Строители / UI
- Карточка строителя приведена к виду карточки специалиста.

### AI / откат обработки
- Команда **`app:ai_parse:reset-builder-queue`** (`AiResetBuilderQueue`): массовый возврат builder-постов в `InQueue` по диапазону дат и провайдеру (`--dry-run`).
- При requeue удаляются старые записи `Builder` с тем же `api_channel_post_id`.
- Фильтры по `ai_date` и AI-провайдеру.

---

## 2026-04-08

### VK как источник данных
- Первая реализация импорта постов из **групп ВКонтакте** (`ReadVkGroups`, `VkApiClient`, команды `app:vk_parse:*`).
- `ApiChannelSourceEnum`: значение `vk`.
- Объявление VK-группы на лендинге.

### Админка
- Mass edit AI-полей и mass delete для сообщений (`ApiChannelPostResource`).

---

## Сводка по новым CLI-командам (за период)

| Команда | Назначение |
|---------|------------|
| `app:vk_parse:specialist\|builder\|company` | Парсинг стен VK |
| `app:ai_parse:reset-builder-queue` | Откат AI-обработки builder-постов |
| `app:catalog:disable-active-builders-hiring-text` | Снятие с публикации карточек с текстом найма |

## Сводка по ключевым env-переменным (за период)

| Переменная | Назначение |
|------------|------------|
| `VK_SERVICE_TOKEN`, `VK_*` | VK API |
| `CATALOG_PUBLICATION_GATE_*` | Ворота публикации в каталог |
| `BUILDER_AI_TWO_PASS_OLLAMA_ENABLED` | Two-pass pipeline для Qwen |
| `MPROTO_PROXY_*`, `MPROTO_IPV6_ENABLED` | MadelineProto сеть |
| `SESSION_LIFETIME` | Таймаут сессии (30 мин) |
