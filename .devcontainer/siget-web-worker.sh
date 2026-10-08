#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
source "${SCRIPT_DIR}/lib-siget.sh"

ensure_directories
cd "${PROJECT_ROOT}"

LOCK_FILE="${STATE_DIR}/watchdog/siget-web-worker.lock"
LOG_FILE="${LOG_DIR}/web-worker-supervisor.log"
mkdir -p "$(dirname "${LOCK_FILE}")"

exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
  exit 0
fi

while true; do
  # Elimina cualquier servidor Laravel legacy antes de tomar :8000.
  stop_legacy_laravel_server
  echo "[$(date -Is)] Iniciando servidor Laravel SIGET en :8000." >> "${LOG_FILE}"

  set +e
  /usr/local/bin/php -d display_errors=0 -d display_startup_errors=0 -d log_errors=1 -S 0.0.0.0:8000 -t "${PROJECT_ROOT}/public" "${PROJECT_ROOT}/.devcontainer/laravel-router.php" >> "${LOG_FILE}" 2>&1
  RC=$?
  set -e

  echo "[$(date -Is)] Laravel terminó con código ${RC}; reiniciando en 2s." >> "${LOG_FILE}"
  sleep 2
done
