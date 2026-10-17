#!/usr/bin/env bash
set -euo pipefail
source "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"
exec 9>/run/lock/siget-operations.lock
flock -n 9 || { echo 'Otra operación de infraestructura está en ejecución.' >&2; exit 1; }
check_repository
compose build app web antivirus
compose exec -T app php artisan down --retry=120
resume_after_upgrade() {
  result=$?
  trap - EXIT
  compose up -d --no-build || result=1
  compose exec -T app php artisan up || result=1
  exit "$result"
}
trap resume_after_upgrade EXIT
compose stop app worker scheduler
compose run --rm --no-deps -T app php artisan migrate --force
compose run --rm --no-deps -T app php artisan optimize:clear
compose up -d --no-build
compose exec -T app php artisan siget:readiness
