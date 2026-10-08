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
  echo "[$(date -Is)] Iniciando servidor Laravel en :8000." >> "${LOG_FILE}"

  set +e
  /usr/local/bin/php artisan serve --host=0.0.0.0 --port=8000 >> "${LOG_FILE}" 2>&1
  RC=$?
  set -e

  echo "[$(date -Is)] Laravel terminó con código ${RC}; reiniciando en 2s." >> "${LOG_FILE}"
  sleep 2
done
