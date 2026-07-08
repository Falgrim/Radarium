#!/usr/bin/env bash
# Диагностика VPN/Xray на uplink-VPS (Часть B плана).
# Запуск на VPS: bash check-vpn-vps.sh
# С prod можно проверить только reachability: bash check-vpn-vps.sh --from-prod 103.90.72.46

set -euo pipefail

FROM_PROD=false
VPS_IP="103.90.72.46"
VLESS_PORT=443

for arg in "$@"; do
  case "$arg" in
    --from-prod) FROM_PROD=true ;;
    --ip=*) VPS_IP="${arg#*=}" ;;
    --port=*) VLESS_PORT="${arg#*=}" ;;
    --help|-h)
      echo "Usage: $0 [--from-prod] [--ip=103.90.72.46] [--port=443]"
      exit 0
      ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

section() { echo; echo "=== $1 ==="; }

if [[ "$FROM_PROD" == true ]]; then
  section "B4. Сквозной тест с prod до VPS $VPS_IP"
  if nc -zv "$VPS_IP" "$VLESS_PORT" 2>&1; then
    echo "OK: TCP $VPS_IP:$VLESS_PORT доступен"
  else
    echo "FAIL: TCP до VPS недоступен"
    exit 1
  fi
  if ss -lntp 2>/dev/null | grep -q 10808; then
    HTTP_CODE="$(curl -x socks5h://127.0.0.1:10808 -m 10 -s -o /dev/null -w '%{http_code}' https://api.telegram.org || echo 000)"
    echo "HTTP via local SOCKS: $HTTP_CODE"
    case "$HTTP_CODE" in
      200|301|302) echo "OK: prod SOCKS → Telegram" ;;
      *) echo "FAIL: SOCKS на prod не работает"; exit 1 ;;
    esac
  else
    echo "WARN: локальный SOCKS 10808 не слушается на этой машине"
  fi
  exit 0
fi

section "B0. Базовая доступность VPS"
uptime
df -h | head -5
free -h 2>/dev/null || true

section "B1. Порты и firewall"
ss -lntp | grep -E ":${VLESS_PORT}|:80" || echo "WARN: порты 443/80 не найдены в ss"
command -v ufw >/dev/null && ufw status verbose || echo "ufw: не установлен"
iptables -L -n 2>/dev/null | head -20 || true

section "B2. Xray-сервер"
if systemctl is-active --quiet xray 2>/dev/null; then
  systemctl status xray --no-pager | head -8
else
  echo "FAIL: xray не active"
  systemctl status xray --no-pager 2>&1 | head -15 || true
  exit 1
fi
journalctl -u xray -b --no-pager -n 50 2>/dev/null || true

XRAY_CONFIG=""
if systemctl cat xray 2>/dev/null | tr ' ' '\n' | grep -qE '\.json$'; then
  XRAY_CONFIG="$(systemctl cat xray 2>/dev/null | tr ' ' '\n' | awk '/\.json$/{print; exit}')"
fi
[[ -z "$XRAY_CONFIG" && -f /usr/local/etc/xray/config.json ]] && XRAY_CONFIG="/usr/local/etc/xray/config.json"
[[ -z "$XRAY_CONFIG" && -f /etc/xray/config.json ]] && XRAY_CONFIG="/etc/xray/config.json"

if [[ -n "$XRAY_CONFIG" && -f "$XRAY_CONFIG" ]]; then
  echo "Config: $XRAY_CONFIG"
  if command -v xray >/dev/null 2>&1; then
    xray -test -config "$XRAY_CONFIG" 2>&1 || echo "WARN: xray -test failed"
  fi
  echo "--- inbound summary (без секретов) ---"
  grep -E '"port"|"protocol"|"dest"|"serverNames"|"shortIds"' "$XRAY_CONFIG" | head -20 || true
else
  echo "WARN: config.json не найден"
fi

section "B3. VPS → Telegram"
TG_CODE="$(curl -m 10 -s -o /dev/null -w '%{http_code}' https://api.telegram.org || echo 000)"
echo "curl api.telegram.org: $TG_CODE"
case "$TG_CODE" in
  200|301|302) echo "OK: VPS достигает Telegram" ;;
  *) echo "WARN/FAIL: VPS не достигает Telegram ($TG_CODE)" ;;
esac
if command -v nc >/dev/null 2>&1; then
  nc -zv 149.154.167.51 443 2>&1 || true
fi

section "B5. Нагрузка"
ss -s 2>/dev/null || true
cat /proc/sys/net/ipv4/ip_forward 2>/dev/null || true
dmesg 2>/dev/null | tail -10 || true

section "Готово (Часть B на VPS)"
