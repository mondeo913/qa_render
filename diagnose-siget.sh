#!/usr/bin/env bash
set -u
cd "${CODESPACE_VSCODE_FOLDER:-$PWD}" 2>/dev/null || true
echo "===== SIGET K2 DIAGNOSTICO ====="
echo "Recovery: ${CODESPACES_RECOVERY_CONTAINER:-false}"
cat /etc/os-release 2>/dev/null | head -8 || true
echo "===== TOOLS ====="
for c in php composer node npm psql pg_isready supervisord mailpit; do
  printf "%-15s " "$c"
  command -v "$c" >/dev/null 2>&1 && command -v "$c" || echo "NO ENCONTRADO"
done
echo "===== LOGS ====="
for f in .codespace/logs/bootstrap.log .codespace/logs/web-error.log .codespace/logs/mailpit.log storage/logs/laravel.log; do
  [ -f "$f" ] && { echo "--- $f"; tail -40 "$f"; }
done
