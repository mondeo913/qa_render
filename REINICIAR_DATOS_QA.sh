#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"

if [[ "${1:-}" != "--confirmar" ]]; then
  echo "Este comando elimina y reconstruye todos los datos QA."
  echo "Ejecute: bash REINICIAR_DATOS_QA.sh --confirmar"
  exit 2
fi

source .devcontainer/lib-siget.sh

start_postgres
php artisan migrate:fresh --seed --force
php artisan optimize:clear
touch "${STATE_DIR}/database-initialized"
start_supervisor

echo "Datos ABCD QA reconstruidos."
