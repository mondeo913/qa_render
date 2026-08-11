#!/usr/bin/env bash
set -u

APP_DIR="/workspaces/qa_render"
PORT="${SIGET_PORT:-8000}"
HOST="0.0.0.0"
HEALTH_URL="http://127.0.0.1:${PORT}/iniciar-sesion"
PID_FILE="${APP_DIR}/.codespace/laravel.pid"
LOG_FILE="${APP_DIR}/.codespace/laravel.log"
LOCK_FILE="${APP_DIR}/.codespace/siget-keepalive.pid"
CHECK_SECONDS="${SIGET_HEALTH_INTERVAL:-20}"

mkdir -p "${APP_DIR}/.codespace"
cd "${APP_DIR}"

echo "$$" > "${LOCK_FILE}"
trap 'rm -f "${LOCK_FILE}"' EXIT INT TERM

is_healthy() {
    command -v curl >/dev/null 2>&1 && curl -fsS --max-time 5 "${HEALTH_URL}" >/dev/null 2>&1
}

pid_is_laravel() {
    local pid="$1"
    [ -n "${pid}" ] || return 1
    kill -0 "${pid}" 2>/dev/null || return 1
    tr '\0' ' ' < "/proc/${pid}/cmdline" 2>/dev/null | grep -q 'artisan serve'
}

find_laravel_pid() {
    pgrep -f 'php artisan serve --host=0.0.0.0 --port=8000' 2>/dev/null | head -n 1 || true
}

stop_laravel() {
    local pid="${1:-}"
    if [ -n "${pid}" ] && kill -0 "${pid}" 2>/dev/null; then
        kill "${pid}" 2>/dev/null || true
        for _ in $(seq 1 10); do
            kill -0 "${pid}" 2>/dev/null || break
            sleep 1
        done
        kill -9 "${pid}" 2>/dev/null || true
    fi
}

start_laravel() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando Laravel en ${HOST}:${PORT}..." >> "${LOG_FILE}"
    nohup php artisan serve --host="${HOST}" --port="${PORT}" >> "${LOG_FILE}" 2>&1 &
    local pid=$!
    echo "${pid}" > "${PID_FILE}"
    sleep 2
    if is_healthy; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Laravel ACTIVO (PID ${pid})." >> "${LOG_FILE}"
        return 0
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Laravel no respondió después de iniciar." >> "${LOG_FILE}"
    return 1
}

# Reuse an already healthy Laravel process. Never create duplicates.
if is_healthy; then
    existing="$(find_laravel_pid)"
    [ -n "${existing}" ] && echo "${existing}" > "${PID_FILE}"
else
    existing="$(find_laravel_pid)"
    [ -n "${existing}" ] && stop_laravel "${existing}"
    start_laravel || true
fi

while true; do
    if is_healthy; then
        sleep "${CHECK_SECONDS}"
        continue
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Health-check falló; reiniciando Laravel..." >> "${LOG_FILE}"
    pid=""
    if [ -f "${PID_FILE}" ]; then
        pid="$(cat "${PID_FILE}" 2>/dev/null || true)"
    fi
    if ! pid_is_laravel "${pid}"; then
        pid="$(find_laravel_pid)"
    fi
    [ -n "${pid}" ] && stop_laravel "${pid}"
    start_laravel || true
    sleep "${CHECK_SECONDS}"
done
