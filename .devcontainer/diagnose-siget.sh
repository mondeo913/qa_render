#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

cd "${PROJECT_ROOT}"

echo "============================================================"
echo " DIAGNÓSTICO SIGET K2 QA"
echo "============================================================"

echo
echo "Versiones"
for tool in php composer node npm psql mailpit; do
  if command -v "${tool}" >/dev/null 2>&1; then
    if [[ "${tool}" == "mailpit" ]]; then "${tool}" version --no-release-check || true; else "${tool}" --version | head -n 2 || true; fi
  else
    echo "FALTA: ${tool}; reconstruya el contenedor SIGET."
  fi
done
if [[ -f "${PGDATA}/PG_VERSION" ]]; then
  echo "Versión del directorio de datos: $(cat "${PGDATA}/PG_VERSION")"
fi
find_postgres_bin || true

echo
show_service_status || true

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
