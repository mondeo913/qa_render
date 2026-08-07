#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${PROJECT_ROOT}"

source "${PROJECT_ROOT}/.devcontainer/lib-siget.sh"

REPORT_FILE="${PROJECT_ROOT}/SIGET_P0_REPORTE_CERTIFICACION.txt"
exec > >(tee "${REPORT_FILE}") 2>&1

echo "============================================================"
echo " SIGET NÚCLEO + ALCANCE - CERTIFICACIÓN BLOQUE P0"
echo "============================================================"
echo "Fecha: $(date -Iseconds)"
echo

echo "1. Extensiones PHP"
assert_php_extensions
echo "OK"

echo
echo "2. PostgreSQL y configuración"
start_postgres
update_env_urls
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
echo "OK"

echo
echo "3. Parser de pauta institucional"
php artisan test --filter=HorizontalPautaParserTest

echo
echo "4. Roles y segregación de funciones"
php artisan test --filter=SpecificRolePermissionsTest
php artisan test --filter=RoleMenuTest

echo
echo "5. Regresión automatizada"
php artisan test

echo
echo "6. Compilación de interfaz"
npm run build

echo
echo "7. Verificación de servicios"
start_supervisor
bash "${PROJECT_ROOT}/.devcontainer/verify-siget.sh"

echo
echo "RESULTADO: BLOQUE P0 CERTIFICADO"
echo "Reporte: ${REPORT_FILE}"
