# Документация Radarium

Вся документация проекта живёт в этом каталоге. Раньше она была разделена между `DOC/` и `docs/`; 07.09.2026 всё собрано здесь.

| Где | Что лежит |
|-----|-----------|
| корень `docs/` | справочники, которые поддерживаются постоянно |
| `docs/runbooks/` | пошаговые инструкции по операциям на production |
| `docs/plans/` | планы и проработки, часть отложена или уже выполнена |
| `docs/reference/` | данные и архивные материалы (промпты, списки, выгрузки) |
| `docs/Logs/` | место для локальных заметок с сервера, содержимое не коммитится |

## Справочники

| Документ | О чём |
|----------|-------|
| [ARTISAN_COMMANDS.md](ARTISAN_COMMANDS.md) | Реестр всех кастомных artisan-команд: назначение, опции, расписание, риски, типовые сценарии эксплуатации |
| [CHANGE_LOG.md](CHANGE_LOG.md) | Журнал изменений проекта с 06.04.2026 |
| [RADARIUM_TECHDOC.md](RADARIUM_TECHDOC.md) | Техдок для разработчика: архитектура, таблицы, пайплайны Telegram/VK/ИИ, MoonShine, env |
| [RADARIUM_OVERVIEW.md](RADARIUM_OVERVIEW.md) | Продуктовое описание: ценность, аудитория, монетизация |

## Runbook'и

**Деплой и инфраструктура**

| Документ | Когда нужен |
|----------|-------------|
| [production-git-pull.md](runbooks/production-git-pull.md) | Обновление кода на progs.com, deploy token, типовые ошибки доступа |
| [queue-worker.md](runbooks/queue-worker.md) | Запуск и эксплуатация `queue:work`, обязательные таймауты |
| [madelineproto-vpn-routing.md](runbooks/madelineproto-vpn-routing.md) | Telegram через Xray VLESS/Reality и локальный SOCKS |
| [telegram-connection-incident-2026-07.md](runbooks/telegram-connection-incident-2026-07.md) | Разбор инцидента с обрывом Telegram (июль–август 2026), решён 06.08 |

**Каталог и очередь ИИ**

| Документ | Когда нужен |
|----------|-------------|
| [diag-builders-catalog.md](runbooks/diag-builders-catalog.md) | В каталоге строителей нет свежих сообщений: разбор вывода `app:catalog:diagnose-builders` по секциям |
| [builder-ai-requeue-missing-visible.md](runbooks/builder-ai-requeue-missing-visible.md) | Пустые карточки в каталоге строителей |
| [specialist-ai-requeue-missing-visible.md](runbooks/specialist-ai-requeue-missing-visible.md) | То же для каталога проектировщиков |
| [specialist-ops-phase1.md](runbooks/specialist-ops-phase1.md) | Ввод в работу каналов проектировщиков: включение, парсинг, ИИ, проверки |
| [specialist-channels-enable.md](runbooks/specialist-channels-enable.md) | Включение отключённых каналов проектировщиков |
| [builders-specialities-rollout.md](runbooks/builders-specialities-rollout.md) | Выкатка справочника специализаций строителей (разовая операция, выполнена) |

## Планы

Рабочими документами не являются — часть выполнена, часть отложена. [PRIORITY_IMPROVEMENTS_PLAN.md](plans/PRIORITY_IMPROVEMENTS_PLAN.md), [DOCKER_COMPOSE_PLAN.md](plans/DOCKER_COMPOSE_PLAN.md) (отложен, production остаётся bare-metal), [E5_IMPLEMENTATION_PLAN.md](plans/E5_IMPLEMENTATION_PLAN.md) (отложен, ИИ работает на YandexGPT и Ollama), [TESTS_IMPLEMENTATION_GUIDE.md](plans/TESTS_IMPLEMENTATION_GUIDE.md) (перечень тестов в нём устарел).

## Данные и архив

См. [reference/README.md](reference/README.md): списки специализаций и VK-групп, архивные версии системных промптов.

## Регламент

Документация обновляется в том же изменении, что и код. Для artisan-команд порядок описан в разделе «Регламент обновления документа» файла [ARTISAN_COMMANDS.md](ARTISAN_COMMANDS.md) и в правиле `.cursor/rules/artisan-commands-doc.mdc`. Значимые изменения фиксируются в [CHANGE_LOG.md](CHANGE_LOG.md).
