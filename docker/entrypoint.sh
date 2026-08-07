#!/bin/sh
set -e
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
if [ "${SIGET_RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi
php artisan storage:link >/dev/null 2>&1 || true
exec "$@"
