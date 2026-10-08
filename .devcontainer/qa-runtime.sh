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

# Recompilar assets Vite cuando el código fuente cambió después del último build.
# Esto evita servir un public/build obsoleto y hace visibles los cambios de interfaz
# sin depender de un build manual después de cada modificación.
if command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
  BUILD_MANIFEST="public/build/manifest.json"
  NEED_BUILD=0
  if [[ ! -f "$BUILD_MANIFEST" ]]; then
    NEED_BUILD=1
  elif find resources/css resources/js -type f -newer "$BUILD_MANIFEST" -print -quit 2>/dev/null | grep -q .; then
    NEED_BUILD=1
  fi

  if [[ "$NEED_BUILD" -eq 1 ]]; then
    echo "Detectados assets frontend nuevos; ejecutando npm run build..."
    npm run build || echo "ADVERTENCIA: no se pudo recompilar Vite; se conserva el último build disponible.";
  fi
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
