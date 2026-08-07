#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

cd "${PROJECT_ROOT}"

assert_php_extensions
start_postgres
update_env_urls
php artisan optimize:clear
start_supervisor

REPORT_FILE="${PROJECT_ROOT}/ULTIMO_REPORTE_PRUEBAS_ABCD_QA.txt"

exec > >(tee "${REPORT_FILE}") 2>&1

echo "============================================================"
echo " SIGET K2 QA - PRUEBAS AUTOMÁTICAS"
echo "============================================================"
echo "Fecha: $(date -Iseconds)"
echo

set +e

echo "1. Pruebas PHPUnit"
php artisan test
TEST_CODE=$?
echo

echo "2. Estado de datos QA"
php artisan siget:qa-status
STATUS_CODE=$?
echo

echo "3. Verificación web"
curl -fsS --max-time 20 "http://127.0.0.1:8000/up"
HEALTH_CODE=$?
echo

curl -fsS --max-time 20 "http://127.0.0.1:8000/iniciar-sesion" >/dev/null
LOGIN_CODE=$?
echo "Inicio de sesión: código ${LOGIN_CODE}"

curl -fsS --max-time 20 "http://127.0.0.1:8025/readyz" >/dev/null
MAIL_CODE=$?
echo "Mailpit: código ${MAIL_CODE}"

echo
show_service_status

echo
echo "CÓDIGOS FINALES"
echo "PHPUnit:      ${TEST_CODE}"
echo "Estado QA:    ${STATUS_CODE}"
echo "Salud:        ${HEALTH_CODE}"
echo "Login:        ${LOGIN_CODE}"
echo "Mailpit:      ${MAIL_CODE}"

if [[ ${TEST_CODE} -eq 0 &&
      ${STATUS_CODE} -eq 0 &&
      ${HEALTH_CODE} -eq 0 &&
      ${LOGIN_CODE} -eq 0 &&
      ${MAIL_CODE} -eq 0 ]]; then
  echo
  echo "RESULTADO: APROBADO"
  FINAL_CODE=0
else
  echo
  echo "RESULTADO: REQUIERE REVISIÓN"
  FINAL_CODE=1
fi

set -e

echo
echo "Reporte: ${REPORT_FILE}"

exit "${FINAL_CODE}"
