#!/usr/bin/env bash
set -euo pipefail
umask 0077
source "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"
exec 9>/run/lock/siget-operations.lock
flock -n 9 || { echo 'Hay otra operación de infraestructura en ejecución.' >&2; exit 1; }
check_repository
compose exec -T app test ! -f storage/framework/down || { echo 'Hay mantenimiento manual activo o la aplicación no está disponible.' >&2; exit 1; }
install -d -m 0750 /var/lib/siget
printf 'backup\n' > /var/lib/siget/infrastructure-maintenance
resume_siget() {
  result=$?
  trap - EXIT
  docker rm --force siget-monthly-operation >/dev/null 2>&1 || true
  if compose up -d --no-build --wait --wait-timeout 180 app worker scheduler; then
    if compose exec -T app php artisan up; then rm -f /var/lib/siget/infrastructure-maintenance; else result=1; fi
  else
    result=1
  fi
  if [[ $result -ne 0 ]]; then logger -p daemon.crit -t siget 'Falló el respaldo mensual o su restauración de prueba. Revisar journalctl -u siget-backup.'; fi
  exit "$result"
}
trap resume_siget EXIT
trap 'exit 143' TERM
trap 'exit 130' INT
compose exec -T app php artisan down --retry=120
compose stop app worker scheduler
backup_result="$(compose run --name siget-monthly-operation --rm --no-deps -T maintenance php artisan siget:backup-create --quiesced --json)"
backup_id="$(printf '%s' "$backup_result" | python3 -c 'import json,sys; print(json.load(sys.stdin)["id"])')"
[[ "$backup_id" =~ ^[a-f0-9-]{36}$ ]] || { echo 'Identificador de respaldo inválido.' >&2; exit 1; }
echo "Integridad del respaldo mensual verificada; iniciando restauración: $backup_id"
# Se ejercita pg_restore en una base aislada; nunca se reemplaza producción.
restore_database="siget_restore_$(date -u +%Y%m%d%H%M%S)"
restore_directory="/srv/siget/backups/.restore-tests/$backup_id"
install -d -o 33 -g 33 -m 0700 "$restore_directory"
compose exec -T database sh -c 'createdb -U "$POSTGRES_USER" -O "$SIGET_APP_DB_USER" "$1"' _ "$restore_database"
compose run --name siget-monthly-operation --rm --no-deps -T maintenance php artisan siget:backup-restore "/backups/$backup_id" --execute --monthly-drill \
  --target-database="$restore_database" --target-repository="/backups/.restore-tests/$backup_id"
compose exec -T database sh -c 'dropdb -U "$POSTGRES_USER" "$1"' _ "$restore_database"
rm -rf -- "$restore_directory"
logger -p daemon.info -t siget "Respaldo mensual y restauración de prueba correctos: $backup_id"
