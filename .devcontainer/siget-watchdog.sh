#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
source "${SCRIPT_DIR}/lib-siget.sh"

ensure_directories

WATCHDOG_DIR="${STATE_DIR}/watchdog"
PID_FILE="${WATCHDOG_DIR}/siget-watchdog.pid"
LOCK_FILE="${WATCHDOG_DIR}/siget-watchdog.lock"
LOG_FILE="${LOG_DIR}/siget-watchdog.log"
INTERVAL="${SIGET_WATCHDOG_INTERVAL:-5}"

mkdir -p "${WATCHDOG_DIR}"

# Evita que postAttach/postStart/ejecuciones manuales creen varios watchdogs.
exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
  exit 0
fi

echo "$$" > "${PID_FILE}"

cleanup() {
  rm -f "${PID_FILE}"
}
trap cleanup EXIT INT TERM

log() {
  printf '[%s] %s\n' "$(date -Is)" "$*" >> "${LOG_FILE}"
}

supervisor_healthy() {
  supervisor_is_running &&
    supervisorctl -c "${SUPERVISOR_CONFIG}" pid >/dev/null 2>&1
}

restart_supervisor() {
  log "Supervisor no responde; reconstruyendo y arrancando Supervisor."
  if supervisor_is_running; then
    supervisorctl -c "${SUPERVISOR_CONFIG}" shutdown >/dev/null 2>&1 || true
    sleep 1
  fi

  rm -f "${SUPERVISOR_SOCKET}" "${SUPERVISOR_PID}"
  start_supervisor
}

ensure_supervisor() {
  if ! supervisor_healthy; then
    restart_supervisor
    return
  fi

  supervisorctl -c "${SUPERVISOR_CONFIG}" reread >/dev/null 2>&1 || true
  supervisorctl -c "${SUPERVISOR_CONFIG}" update >/dev/null 2>&1 || true
}

ensure_program() {
  local program="$1"

  if ! supervisor_healthy; then
    return
  fi

  if ! supervisorctl -c "${SUPERVISOR_CONFIG}" status "$program" 2>/dev/null | grep -q 'RUNNING'; then
    log "$program no está RUNNING; iniciándolo."
    supervisorctl -c "${SUPERVISOR_CONFIG}" start "$program" >/dev/null 2>&1 || true
  fi
}

ensure_web() {
  if ! curl -fsS --max-time 8 http://127.0.0.1:8000/up >/dev/null 2>&1; then
    log "SIGET en :8000 no responde; reiniciando siget-web."
    if supervisor_healthy; then
      supervisorctl -c "${SUPERVISOR_CONFIG}" restart siget-web >/dev/null 2>&1 || true
    fi
  fi
}

ensure_mailpit() {
  if ! curl -fsS --max-time 8 http://127.0.0.1:8025/readyz >/dev/null 2>&1; then
    log "Mailpit en :8025 no responde; reiniciando siget-mailpit."
    if supervisor_healthy; then
      supervisorctl -c "${SUPERVISOR_CONFIG}" restart siget-mailpit >/dev/null 2>&1 || true
    fi
  fi
}

log "Watchdog SIGET iniciado. Intervalo=${INTERVAL}s."

while true; do
  # PostgreSQL tiene su propio watchdog bajo Supervisor, pero este watchdog
  # también lo recupera cuando Supervisor completo está caído.
  if ! postgres_is_ready; then
    log "PostgreSQL en :${PGPORT} no responde; ejecutando recuperación."
    start_postgres >> "${LOG_FILE}" 2>&1 || log "La recuperación de PostgreSQL falló; se reintentará."
  fi

  ensure_supervisor

  if supervisor_healthy; then
    ensure_program siget-web
    ensure_program siget-queue
    ensure_program siget-scheduler
    ensure_program siget-mailpit
    ensure_program siget-postgres-watchdog

    ensure_web
    ensure_mailpit
  fi

  sleep "${INTERVAL}"
done
