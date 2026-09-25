#!/usr/bin/env bash
set -Eeuo pipefail
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}"
source .devcontainer/lib-siget.sh
ensure_directories

# Mantener las sesiones de QA activas durante la jornada.
if [[ -f .env ]]; then
  if grep -q '^SESSION_LIFETIME=' .env; then sed -i 's/^SESSION_LIFETIME=.*/SESSION_LIFETIME=1440/' .env; else printf '\nSESSION_LIFETIME=1440\n' >> .env; fi
  if grep -q '^SESSION_EXPIRE_ON_CLOSE=' .env; then sed -i 's/^SESSION_EXPIRE_ON_CLOSE=.*/SESSION_EXPIRE_ON_CLOSE=false/' .env; else printf 'SESSION_EXPIRE_ON_CLOSE=false\n' >> .env; fi
fi

# El arranque es idempotente: inicia PostgreSQL y Supervisor solo si hace falta.
if ! postgres_is_ready || ! supervisor_is_running; then
  bash .devcontainer/start-siget.sh
fi
php artisan optimize:clear >/dev/null 2>&1 || true
show_service_status
