#!/usr/bin/env bash
# Usage: ./switch-phase.sh 2-read-only-slice     (local Laragon nginx)
# Copies phases/<name>.conf to routing.conf, checks the config, then reloads with zero downtime.
set -euo pipefail
cd "$(dirname "$0")"
NGINX="${NGINX:-/c/laragon/bin/nginx/nginx-1.30.4/nginx.exe}"
PREFIX="$(pwd -W 2>/dev/null || pwd)/"

phase="${1:?phase name, e.g. 1-shadow}"
[ -f "phases/${phase}.conf" ] || { echo "No such phase: phases/${phase}.conf"; exit 1; }

cp "phases/${phase}.conf" routing.conf
"$NGINX" -p "$PREFIX" -c nginx.conf -t
"$NGINX" -p "$PREFIX" -c nginx.conf -s reload 2>/dev/null || "$NGINX" -p "$PREFIX" -c nginx.conf &
echo "Facade now running phase: ${phase}"
