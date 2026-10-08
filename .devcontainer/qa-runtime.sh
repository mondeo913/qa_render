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

# El build de Vite está ignorado por Git (/public/build), por lo que un checkout/reset
# puede dejar un bundle de otra versión. Se ata el build al SHA exacto de main.
if command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
  BUILD_MANIFEST="public/build/manifest.json"
  BUILD_STAMP="public/build/.siget-build-commit"
  CURRENT_COMMIT="$(git rev-parse HEAD 2>/dev/null || true)"
  BUILT_COMMIT="$(cat "$BUILD_STAMP" 2>/dev/null || true)"
  NEED_BUILD=0

  if [[ ! -f "$BUILD_MANIFEST" || "$BUILT_COMMIT" != "$CURRENT_COMMIT" ]]; then
    NEED_BUILD=1
  fi

  if [[ "$NEED_BUILD" -eq 1 ]]; then
    echo "Build frontend no corresponde al commit ${CURRENT_COMMIT}; ejecutando npm run build..."
    if npm run build; then
      printf "%s\n" "$CURRENT_COMMIT" > "$BUILD_STAMP"
      echo "Build Vite actualizado para ${CURRENT_COMMIT}."
    else
      echo "ERROR: npm run build falló; no se marcará el build como vigente."
    fi
  fi
fi

# Eliminar cualquier servidor Laravel antiguo antes de levantar el nuevo router.
stop_legacy_laravel_server

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

# Mantener un proceso externo a Supervisor supervisando toda la pila.
# Esto permite recuperar Supervisor, Laravel, Mailpit y PostgreSQL aunque
# Supervisor completo se caiga.
WATCHDOG_SCRIPT="${PROJECT_ROOT}/.devcontainer/siget-watchdog.sh"
WATCHDOG_LOG="${LOG_DIR}/siget-watchdog-launcher.log"
if [[ -f "${WATCHDOG_SCRIPT}" ]]; then
  chmod +x "${WATCHDOG_SCRIPT}" >/dev/null 2>&1 || true
  nohup bash "${WATCHDOG_SCRIPT}" >> "${WATCHDOG_LOG}" 2>&1 &
  echo "Watchdog SIGET verificado/iniciado en segundo plano."
fi

show_service_status
