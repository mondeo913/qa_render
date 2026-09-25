#!/usr/bin/env bash
set -Eeuo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
source "${SCRIPT_DIR}/lib-siget.sh"
ensure_directories

while true; do
  if ! postgres_is_ready; then
    printf '[%s] PostgreSQL no responde; intentando recuperación.\n' "$(date -Is)" >> "${LOG_DIR}/postgres-watchdog.log"
    if start_postgres >> "${LOG_DIR}/postgres-watchdog.log" 2>&1; then
      printf '[%s] PostgreSQL recuperado.\n' "$(date -Is)" >> "${LOG_DIR}/postgres-watchdog.log"
    else
      printf '[%s] La recuperación falló; se reintentará.\n' "$(date -Is)" >> "${LOG_DIR}/postgres-watchdog.log"
    fi
  fi
  sleep 5
done
