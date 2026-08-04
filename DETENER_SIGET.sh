#!/usr/bin/env bash
set -Eeuo pipefail

cd /workspaces/qa_render

stop_pidfile() {
  local file="$1"
  if [[ -f "$file" ]]; then
    local pid
    pid="$(cat "$file" 2>/dev/null || true)"
    if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
      for _ in $(seq 1 10); do
        kill -0 "$pid" 2>/dev/null || break
        sleep 1
      done
      kill -9 "$pid" 2>/dev/null || true
    fi
    rm -f "$file"
  fi
}

stop_pidfile storage/framework/siget-server.pid
stop_pidfile storage/framework/siget-scheduler.pid

pkill -f 'php artisan serve .*--port=8000' 2>/dev/null || true
pkill -f 'php artisan schedule:work' 2>/dev/null || true

echo "SIGET fue detenido. PostgreSQL permanece activo para un arranque rápido."
