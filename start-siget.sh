#!/usr/bin/env bash
set -u
ROOT="${CODESPACE_VSCODE_FOLDER:-/workspaces/$(basename "${PWD}")}"
[ -d "$ROOT" ] || ROOT="$PWD"
cd "$ROOT" || exit 0
mkdir -p .codespace/logs

bash .devcontainer/bootstrap-siget.sh >/dev/null 2>&1 || true

if command -v pg_ctlcluster >/dev/null 2>&1; then
  pg_ver="$(ls /etc/postgresql 2>/dev/null | sort -V | tail -1 || true)"
  [ -n "$pg_ver" ] && pg_ctlcluster "$pg_ver" main start >/dev/null 2>&1 || true
fi

if command -v mailpit >/dev/null 2>&1 && ! pgrep -x mailpit >/dev/null 2>&1; then
  nohup mailpit --listen=0.0.0.0:1025 --ui-bind-addr=0.0.0.0:8025 \
    >.codespace/logs/mailpit.log 2>&1 &
fi

if [ -f artisan ] && command -v php >/dev/null 2>&1; then
  if ! pgrep -f "artisan serve.*--port=8000" >/dev/null 2>&1; then
    nohup php artisan serve --host=0.0.0.0 --port=8000 \
      >.codespace/logs/web.log 2>.codespace/logs/web-error.log &
  fi
fi

echo "SIGET:   http://localhost:8000"
echo "Mailpit: http://localhost:8025"
exit 0
