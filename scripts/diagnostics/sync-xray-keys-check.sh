#!/usr/bin/env bash
# B6: сверка UUID/Reality между клиентом (prod) и сервером (VPS).
# Использование:
#   bash sync-xray-keys-check.sh /path/to/prod-client-config.json /path/to/vps-server-config.json
#   bash sync-xray-keys-check.sh /etc/xray/config.json --remote root@103.90.72.46:/usr/local/etc/xray/config.json

set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 CLIENT_CONFIG SERVER_CONFIG_OR_SSH_PATH"
  exit 2
fi

CLIENT="$1"
SERVER_SPEC="$2"
TMP_SERVER=""

cleanup() { [[ -n "$TMP_SERVER" && -f "$TMP_SERVER" ]] && rm -f "$TMP_SERVER"; }
trap cleanup EXIT

if [[ "$SERVER_SPEC" == --remote=* ]]; then
  REMOTE="${SERVER_SPEC#--remote=}"
  TMP_SERVER="$(mktemp)"
  scp -q "$REMOTE" "$TMP_SERVER"
  SERVER="$TMP_SERVER"
else
  SERVER="$SERVER_SPEC"
fi

for f in "$CLIENT" "$SERVER"; do
  if [[ ! -f "$f" ]]; then
    echo "FAIL: файл не найден: $f" >&2
    exit 1
  fi
done

show_keys() {
  local label="$1"
  local file="$2"
  echo "=== $label: $file ==="
  grep -E '"id"|"address"|"port"|"flow"|"serverName"|"publicKey"|"shortId"|"shortIds"|"dest"|"privateKey"' "$file" \
    | sed 's/^[[:space:]]*//' || true
  echo
}

show_keys "Клиент (prod)" "$CLIENT"
show_keys "Сервер (VPS)" "$SERVER"

echo "Сверьте вручную:"
echo "  - vnext.address / port на клиенте = listen на VPS"
echo "  - users.id (UUID) на клиенте ∈ clients на сервере"
echo "  - realitySettings: serverName, publicKey, shortId (клиент) ↔ serverNames, privateKey, shortIds (сервер)"
