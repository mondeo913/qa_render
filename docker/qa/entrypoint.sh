#!/usr/bin/env bash
set -Eeuo pipefail
cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions \
  storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

printf 'Esperando PostgreSQL en %s:%s...\n' "${DB_HOST:-postgres}" "${DB_PORT:-5432}"
for i in $(seq 1 90); do
  if pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" \
      -U "${DB_USERNAME:-siget}" -d "${DB_DATABASE:-siget_qa}" >/dev/null 2>&1; then
    break
  fi
  if [ "$i" -eq 90 ]; then
    echo 'PostgreSQL no respondió a tiempo.' >&2
    exit 20
  fi
  sleep 2
done

php artisan optimize:clear
php artisan migrate --force
php artisan siget:qa-init || php artisan db:seed --force
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/siget.conf
