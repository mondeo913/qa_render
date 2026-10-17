#!/usr/bin/env bash
set -euo pipefail
[[ "${SIGET_PRODUCTION_SMOKE:-}" == 1 ]] || { echo 'Sólo para aceptación aislada de CI.' >&2; exit 1; }
siget_test_root="$(mktemp -d /tmp/siget-production-XXXXXX)"
siget_test_env="$siget_test_root/.env.production"
export SIGET_CI_ADMIN_PASSWORD="A1!$(openssl rand -hex 24)"
export SIGET_CI_CERT="$siget_test_root/tls/fullchain.pem"
mkdir -p "$siget_test_root/repository" "$siget_test_root/backups" "$siget_test_root/tls"
siget_repository_id="$(cat /proc/sys/kernel/random/uuid)"
printf '%s\n' "$siget_repository_id" > "$siget_test_root/repository/.siget-repository-id"
sudo chown -R 33:33 "$siget_test_root/repository" "$siget_test_root/backups"
sudo chmod 0750 "$siget_test_root" "$siget_test_root/repository"
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj '/CN=localhost' -addext 'subjectAltName=DNS:localhost' \
  -keyout "$siget_test_root/tls/privkey.pem" -out "$SIGET_CI_CERT" >/dev/null 2>&1
cat > "$siget_test_env" <<ENV
APP_URL=https://localhost
APP_KEY=base64:$(openssl rand -base64 32)
DB_DATABASE=siget_ci_production
DB_USERNAME=siget_app
DB_PASSWORD=$(openssl rand -hex 24)
SIGET_DATABASE_ADMIN_PASSWORD=$(openssl rand -hex 24)
SIGET_REPOSITORY_ID=$siget_repository_id
SIGET_REPOSITORY_PATH=$siget_test_root/repository
SIGET_BACKUP_PATH=$siget_test_root/backups
SIGET_TLS_PATH=$siget_test_root/tls
SIGET_BACKUP_MIN_FREE_GB=0
SIGET_METRICS_TOKEN=$(openssl rand -hex 24)
SIGET_SUPPORT_EMAIL=soporte@institucion.test
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_FROM_ADDRESS=siget@institucion.test
SIGET_CI_ISOLATED=1
ENV
chmod 0600 "$siget_test_env"
compose() { docker compose --env-file "$siget_test_env" -f deploy/vm1/compose.yml -p siget_ci_production "$@"; }
cleanup() {
  result=$?
  trap - EXIT
  if [[ $result -ne 0 ]]; then compose logs --tail=65 app web database antivirus worker || true; fi
  compose down --volumes --remove-orphans || true
  sudo rm -rf -- "$siget_test_root"
  exit "$result"
}
trap cleanup EXIT
compose config --quiet
compose up -d --wait --wait-timeout 600 database antivirus
compose run --rm --no-deps -T app php artisan migrate --force
compose run --rm --no-deps -T app php deploy/tests/production-fixture.php clean
printf '%s\n' "$SIGET_CI_ADMIN_PASSWORD" | compose run --rm --no-deps -T app php artisan siget:install \
  --admin-name='Administrador de aceptación' --admin-email=admin@institucion.test --password-stdin
compose up -d --wait --wait-timeout 600
curl --fail --silent --show-error --cacert "$SIGET_CI_CERT" https://localhost/ready
curl --fail --silent --show-error --cacert "$SIGET_CI_CERT" https://localhost/login > /dev/null
# The database owner secret is not passed to PHP containers.
compose exec -T app php -r 'if (getenv("SIGET_DATABASE_ADMIN_PASSWORD")) exit(1); echo "APP SECRET SEPARATION PASS\n";'
pauta_year="$(compose exec -T app php deploy/tests/production-fixture.php make-pauta)"
compose cp app:/tmp/siget-ci-pauta.xlsx "$siget_test_root/pauta.xlsx"
python3 deploy/tests/production-api.py "$siget_test_env" "$siget_test_root/pauta.xlsx" "$pauta_year"
# Validate actual restart policy after an unexpected worker termination.
worker_id="$(compose ps -q worker)"
docker kill "$worker_id" >/dev/null
for attempt in $(seq 1 20); do
  if [[ $(docker inspect --format '{{.State.Running}}' "$worker_id") == true ]]; then break; fi
  sleep 1
done
[[ $(docker inspect --format '{{.State.Running}}' "$worker_id") == true ]]
[[ $(docker inspect --format '{{.RestartCount}}' "$worker_id") -ge 1 ]]
echo 'WORKER AUTOMATIC RESTART PASS'
# A missing private repository identifier must stop readiness and access.
sudo mv "$siget_test_root/repository/.siget-repository-id" "$siget_test_root/repository/.siget-repository-id-missing"
status="$(curl --silent --cacert "$SIGET_CI_CERT" -o /dev/null -w '%{http_code}' https://localhost/ready)"
[[ "$status" == 503 ]]
sudo mv "$siget_test_root/repository/.siget-repository-id-missing" "$siget_test_root/repository/.siget-repository-id"
echo 'PRIVATE REPOSITORY FAILURE BLOCK PASS'
# Quiesce the very same writers as the monthly systemd operation.
compose exec -T app php artisan down --retry=120
compose stop app worker scheduler
backup_json="$(compose run --rm --no-deps -T maintenance php artisan siget:backup-create --quiesced --json)"
backup_id="$(printf '%s' "$backup_json" | python3 -c 'import sys,json; print(json.load(sys.stdin)["id"])')"
compose exec -T database sh -c 'createdb -U "$POSTGRES_USER" -O "$SIGET_APP_DB_USER" siget_restore_acceptance'
sudo install -d -o 33 -g 33 -m 0700 "$siget_test_root/backups/.restore-tests/acceptance"
compose run --rm --no-deps -T maintenance php artisan siget:backup-restore "/backups/$backup_id" --execute \
  --target-database=siget_restore_acceptance --target-repository=/backups/.restore-tests/acceptance
compose run --rm --no-deps -T maintenance php deploy/tests/production-fixture.php assert-restored /backups/.restore-tests/acceptance
compose run --rm --no-deps -T maintenance php artisan siget:backup-verify "$backup_id"
if compose run --rm --no-deps -T maintenance php artisan siget:backup-restore "/backups/$backup_id" --execute \
    --target-database=siget_ci_production --target-repository=/backups/.restore-tests/acceptance; then
  echo 'La restauración no debe reemplazar la base activa.' >&2; exit 1
fi
if compose run --rm --no-deps -T -e SIGET_BACKUP_MIN_FREE_GB=999999 maintenance php artisan siget:backup-create --quiesced; then
  echo 'El respaldo debe detenerse si no existe espacio suficiente.' >&2; exit 1
fi
echo 'MONTHLY BACKUP + LIVE DATABASE PROTECTION + CAPACITY GUARD PASS'
sudo cp -a "$siget_test_root/backups/$backup_id" "$siget_test_root/backups/corrupted"
printf 'corrupted' | sudo tee "$siget_test_root/backups/corrupted/database.dump" >/dev/null
if compose run --rm --no-deps -T maintenance php artisan siget:backup-restore /backups/corrupted; then
  echo 'Un respaldo alterado no debe superar la verificación.' >&2; exit 1
fi
echo 'CORRUPTED BACKUP REJECTION PASS'
compose up -d --no-build
compose exec -T app php artisan up
compose exec -T app php artisan siget:backup-status
curl --fail --silent --show-error --cacert "$SIGET_CI_CERT" https://localhost/ready
echo 'PRODUCTION ENVIRONMENT ACCEPTANCE PASS (repository bind fixture; real NFS accepted on target VMs by installer)'
