#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

ensure_directories
cd "${PROJECT_ROOT}"

if [[ ! -f "${STATE_DIR}/application-installed" ]]; then
  bash "${PROJECT_ROOT}/.devcontainer/install-siget.sh"
  exit 0
fi

start_postgres
update_env_urls
php artisan optimize:clear >/dev/null
start_supervisor

wait_for_http "http://127.0.0.1:8000/up" "SIGET" 120
wait_for_http "http://127.0.0.1:8025/readyz" "Mailpit" 90

echo
echo "SIGET:   $(app_url)/iniciar-sesion"
echo "Mailpit: $(mailpit_url)"
echo "Accesos: ${STATE_DIR}/ACCESOS.txt"
