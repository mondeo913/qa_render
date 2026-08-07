#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"
source .devcontainer/lib-siget.sh

start_postgres
mkdir -p RESPALDOS

backup_file="RESPALDOS/SIGET_QA_$(date +%Y%m%d_%H%M%S).sql"

pg_dump \
  -h 127.0.0.1 \
  -p "${PGPORT}" \
  -U "${PGUSER}" \
  -d "${PGDATABASE}" \
  > "${backup_file}"

echo "Respaldo creado: ${backup_file}"
