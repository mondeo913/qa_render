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

# Recuperación idempotente: una sesión inactiva nunca debe dejar a SIGET
# apuntando a un PostgreSQL apagado. Se recupera solo lo que esté caído.
start_postgres

if ! supervisor_is_running; then
  start_supervisor
else
  supervisorctl -c "${SUPERVISOR_CONFIG}" reread >/dev/null 2>&1 || true
  supervisorctl -c "${SUPERVISOR_CONFIG}" update >/dev/null 2>&1 || true

  for program in siget-web siget-queue siget-scheduler siget-mailpit siget-postgres-watchdog; do
    if ! supervisorctl -c "${SUPERVISOR_CONFIG}" status "${program}" 2>/dev/null | grep -q 'RUNNING'; then
      supervisorctl -c "${SUPERVISOR_CONFIG}" start "${program}" >/dev/null 2>&1 || true
    fi
  done
fi

php artisan optimize:clear >/dev/null 2>&1 || true
show_service_status
