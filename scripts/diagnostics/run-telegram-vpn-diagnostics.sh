#!/usr/bin/env bash
# Мастер-скрипт диагностики Telegram + VPN (план telegram-vpn-healthcheck).
# Prod:  bash scripts/diagnostics/run-telegram-vpn-diagnostics.sh prod [--smoke]
# VPS:   bash scripts/diagnostics/run-telegram-vpn-diagnostics.sh vps
# Sync:  bash scripts/diagnostics/run-telegram-vpn-diagnostics.sh sync CLIENT.json USER@103.90.72.46:/path/config.json

set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"

usage() {
  echo "Usage:"
  echo "  $0 prod [--smoke] [--reset-ipc=API_ID]"
  echo "  $0 vps [--from-prod]"
  echo "  $0 sync CLIENT_CONFIG --remote=USER@HOST:/path/to/config.json"
  echo "  $0 restore"
  exit 2
}

[[ $# -ge 1 ]] || usage
MODE="$1"
shift

case "$MODE" in
  prod)
    ARGS=()
    for a in "$@"; do ARGS+=("$a"); done
    exec bash "$DIR/check-telegram-madelineproto-prod.sh" "${ARGS[@]}"
    ;;
  vps)
    exec bash "$DIR/check-vpn-vps.sh" "$@"
    ;;
  sync)
    [[ $# -ge 2 ]] || usage
    exec bash "$DIR/sync-xray-keys-check.sh" "$@"
    ;;
  restore)
    exec bash "$DIR/restore-telegram-cron.sh"
    ;;
  *)
    usage
    ;;
esac
