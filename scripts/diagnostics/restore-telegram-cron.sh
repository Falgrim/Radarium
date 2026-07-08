#!/usr/bin/env bash
# A8: возврат эксплуатации после успешной диагностики.
# Запуск из корня проекта: bash scripts/diagnostics/restore-telegram-cron.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
PHP="${PHP_BIN:-/opt/php83/bin/php}"

echo "=== A8. Восстановление эксплуатации ==="
"$PHP" artisan config:cache
echo "OK: config:cache"

echo
echo "Проверьте crontab вручную — должна быть строка schedule:run, например:"
echo "  * * * * * exec /opt/php83/bin/php $ROOT/artisan schedule:run >> /dev/null 2>&1"
echo
echo "Рассылку включайте только после стабильного парсинга:"
echo "  SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=true в .env"
echo "  затем: $PHP artisan config:cache"
