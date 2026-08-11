#!/usr/bin/env bash
set -u

cd /workspaces/qa_render
mkdir -p .codespace

# QA sessions: 24 hours and do not expire just because the browser tab closes.
if [ -f .env ]; then
    if grep -q '^SESSION_LIFETIME=' .env; then
        sed -i 's/^SESSION_LIFETIME=.*/SESSION_LIFETIME=1440/' .env
    else
        printf '\nSESSION_LIFETIME=1440\n' >> .env
    fi
    if grep -q '^SESSION_EXPIRE_ON_CLOSE=' .env; then
        sed -i 's/^SESSION_EXPIRE_ON_CLOSE=.*/SESSION_EXPIRE_ON_CLOSE=false/' .env
    else
        printf 'SESSION_EXPIRE_ON_CLOSE=false\n' >> .env
    fi
fi

php artisan optimize:clear >/dev/null 2>&1 || true

WATCHDOG_PID_FILE=".codespace/siget-keepalive.pid"
if [ -f "${WATCHDOG_PID_FILE}" ]; then
    pid="$(cat "${WATCHDOG_PID_FILE}" 2>/dev/null || true)"
    if [ -n "${pid}" ] && kill -0 "${pid}" 2>/dev/null; then
        exit 0
    fi
fi

nohup bash .devcontainer/siget-keepalive.sh >/dev/null 2>&1 &
