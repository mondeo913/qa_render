#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

cd "${PROJECT_ROOT}"

echo "============================================================"
echo " DIAGNÓSTICO SIGET K2 QA"
echo "============================================================"

echo
echo "Versiones"
php -v | head -n 2
composer --version
node --version
npm --version
psql --version
mailpit version

echo
show_service_status

echo
echo "Laravel"
php artisan about || true

echo
echo "Migraciones"
php artisan migrate:status || true

echo
echo "Últimos logs"
for log_file in \
  "${LOG_DIR}/web-error.log" \
  "${LOG_DIR}/web.log" \
  "${LOG_DIR}/queue-error.log" \
  "${LOG_DIR}/scheduler-error.log" \
  "${LOG_DIR}/postgres.log" \
  "${PROJECT_ROOT}/storage/logs/laravel.log"; do
  echo
  echo "----- ${log_file} -----"

  if [[ -f "${log_file}" ]]; then
    tail -n 120 "${log_file}"
  else
    echo "No existe."
  fi
done
