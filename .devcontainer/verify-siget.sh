#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

cd "${PROJECT_ROOT}"

assert_php_extensions
start_postgres
start_supervisor

REPORT_FILE="${PROJECT_ROOT}/ULTIMO_REPORTE_QA.txt"

{
  echo "SIGET K2 QA - VERIFICACIÓN"
  echo "Fecha: $(date -Iseconds)"
  echo

  curl -fsS --max-time 20 "http://127.0.0.1:8000/up" >/dev/null
  echo "OK  Salud Laravel /up"

  curl -fsS --max-time 20 "http://127.0.0.1:8000/iniciar-sesion" >/dev/null
  echo "OK  Pantalla de inicio de sesión"

  curl -fsS --max-time 20 "http://127.0.0.1:8025/readyz" >/dev/null
  echo "OK  Mailpit"

  php artisan migrate:status >/dev/null
  echo "OK  Migraciones"

  php artisan siget:qa-status
  echo
  show_service_status
} | tee "${REPORT_FILE}"

echo
echo "Reporte generado: ${REPORT_FILE}"
