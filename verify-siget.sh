#!/usr/bin/env bash
set -u
cd "${CODESPACE_VSCODE_FOLDER:-$PWD}" 2>/dev/null || true
echo "===== SIGET K2 HEALTH CHECK ====="
for c in php composer node npm psql pg_isready supervisord mailpit; do
  command -v "$c" >/dev/null 2>&1 && echo "OK   $c" || echo "FAIL $c"
done
[ -f artisan ] && echo "OK   artisan" || echo "FAIL artisan"
[ -f .env ] && echo "OK   .env" || echo "WARN .env"
if command -v curl >/dev/null 2>&1; then
  curl -fsS --max-time 5 http://127.0.0.1:8000/up >/dev/null 2>&1 && echo "OK   Laravel /up" || echo "WARN Laravel /up"
  curl -fsS --max-time 5 http://127.0.0.1:8025 >/dev/null 2>&1 && echo "OK   Mailpit" || echo "WARN Mailpit"
fi
