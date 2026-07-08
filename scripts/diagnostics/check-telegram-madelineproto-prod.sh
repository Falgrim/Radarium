#!/usr/bin/env bash
# Диагностика MadelineProto на prod (Часть A плана telegram-vpn-healthcheck).
# Запуск из корня проекта: bash scripts/diagnostics/check-telegram-madelineproto-prod.sh
# Опции: --smoke (запустить app:tg_parse:*), --reset-ipc API_ID, --skip-isolation

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-/opt/php83/bin/php}"
RUN_SMOKE=false
RESET_IPC=""
SKIP_ISOLATION=false

for arg in "$@"; do
  case "$arg" in
    --smoke) RUN_SMOKE=true ;;
    --skip-isolation) SKIP_ISOLATION=true ;;
    --reset-ipc=*) RESET_IPC="${arg#*=}" ;;
    --help|-h)
      echo "Usage: $0 [--smoke] [--reset-ipc=API_ID] [--skip-isolation]"
      exit 0
      ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

section() { echo; echo "=== $1 ==="; }

section "A0. Изоляция"
if [[ "$SKIP_ISOLATION" == false ]]; then
  if [[ -f .env ]]; then
    grep -E '^SCHEDULE_TG_CHAT_SEND_COMPANY_ENABLED=' .env || true
  else
    echo "WARN: .env не найден"
  fi
  pkill -f "MadelineProto worker" 2>/dev/null || true
  if pgrep -fa MadelineProto >/dev/null 2>&1; then
    echo "FAIL: процессы MadelineProto ещё запущены:"
    pgrep -fa MadelineProto || true
    exit 1
  fi
  echo "OK: MadelineProto worker не запущен"
else
  echo "SKIP: изоляция пропущена (--skip-isolation)"
fi

section "A1. MPROTO_* и config"
if [[ -f .env ]]; then
  grep -E '^MPROTO_' .env || echo "WARN: MPROTO_* не заданы в .env"
else
  echo "FAIL: нет .env"
  exit 1
fi
"$PHP" artisan config:clear
echo "OK: config:clear"

section "A2. Xray-клиент (SOCKS 10808)"
if systemctl is-active --quiet xray 2>/dev/null; then
  systemctl status xray --no-pager | head -5
else
  echo "WARN: xray не active — пробуем status:"
  systemctl status xray --no-pager 2>&1 | head -10 || true
fi
if ss -lntp 2>/dev/null | grep -q 10808; then
  ss -lntp | grep 10808 || true
  echo "OK: порт 10808 слушается"
else
  echo "FAIL: порт 10808 не слушается"
  journalctl -u xray -b --no-pager -n 30 2>/dev/null || true
  exit 1
fi

XRAY_CONFIG=""
if systemctl cat xray 2>/dev/null | grep -qE '\-c\s+'; then
  XRAY_CONFIG="$(systemctl cat xray 2>/dev/null | tr ' ' '\n' | awk '/\.json$/{print; exit}')"
fi
[[ -z "$XRAY_CONFIG" && -f /etc/xray/config.json ]] && XRAY_CONFIG="/etc/xray/config.json"
[[ -z "$XRAY_CONFIG" && -f /usr/local/etc/xray/config.json ]] && XRAY_CONFIG="/usr/local/etc/xray/config.json"
if [[ -n "$XRAY_CONFIG" && -f "$XRAY_CONFIG" ]]; then
  echo "Xray config: $XRAY_CONFIG"
  grep -E '"address"|"port"' "$XRAY_CONFIG" | head -6 || true
fi

section "A3. SOCKS → api.telegram.org"
HTTP_CODE="$(curl -x socks5h://127.0.0.1:10808 -m 10 -s -o /dev/null -w '%{http_code}' https://api.telegram.org || echo 000)"
echo "HTTP via SOCKS: $HTTP_CODE"
case "$HTTP_CODE" in
  200|301|302) echo "OK: SOCKS + uplink работают" ;;
  *) echo "FAIL: ожидались 200/301/302, получено $HTTP_CODE — см. Часть B (VPS)"; exit 1 ;;
esac

if command -v redis-cli >/dev/null 2>&1; then
  REDIS_PING="$(redis-cli ping 2>/dev/null || echo FAIL)"
  echo "redis-cli ping: $REDIS_PING"
  [[ "$REDIS_PING" == "PONG" ]] || echo "WARN: Redis недоступен"
fi

section "A4. Сессии MadelineProto"
ls -la session.madeline.* 2>/dev/null || echo "WARN: session.madeline.* не найдены"
if [[ -n "$RESET_IPC" ]]; then
  SESSION_DIR="session.madeline.${RESET_IPC}"
  if [[ -d "$SESSION_DIR" ]]; then
    rm -f "${SESSION_DIR}/ipcState.php"
    find "$SESSION_DIR" -maxdepth 1 \( -name 'ipc' -o -name 'callback.ipc' \) -delete
    echo "OK: IPC сброшен для $SESSION_DIR"
  else
    echo "WARN: каталог $SESSION_DIR не найден"
  fi
fi

section "A6. Логи (последние 120 строк)"
for log in storage/logs/MadelineProto.log storage/logs/post_parser.log; do
  if [[ -f "$log" ]]; then
    echo "--- $log ---"
    tail -n 120 "$log"
  else
    echo "WARN: $log отсутствует"
  fi
done

if [[ "$RUN_SMOKE" == true ]]; then
  section "A5. Smoke-тест MadelineProto"
  for cmd in app:tg_parse:builder app:tg_parse:company app:tg_parse:specialist; do
    echo "--- $cmd ---"
    "$PHP" artisan "$cmd"
  done
  echo "OK: smoke-тесты завершены"
else
  echo
  echo "Подсказка: для smoke-теста запустите с --smoke"
  echo "  bash scripts/diagnostics/check-telegram-madelineproto-prod.sh --smoke"
fi

section "Готово (Часть A)"
echo "При ошибках сессии: php artisan app:tg_auth --api-id=... --api-hash=... --qr --reset"
