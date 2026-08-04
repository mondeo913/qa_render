#!/usr/bin/env bash
set -Eeuo pipefail

REPO="/workspaces/qa_render"
cd "$REPO"

chmod +x ARRANCAR_SIGET.sh DETENER_SIGET.sh 2>/dev/null || true

echo "=== Preparando PostgreSQL ==="
service postgresql start

if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='siget'" | grep -q 1; then
  runuser -u postgres -- psql -c "CREATE ROLE siget LOGIN PASSWORD 'siget_qa_password';"
fi

if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_database WHERE datname='siget_qa'" | grep -q 1; then
  runuser -u postgres -- createdb -O siget siget_qa
fi

if [[ ! -f artisan || ! -f composer.json ]]; then
  echo "SIGET todavía no está completo en esta rama."
  echo "El entorno ya quedó listo; cuando existan artisan y composer.json, ejecute: bash ARRANCAR_SIGET.sh"
  exit 0
fi

set_env() {
  local key="$1" value="$2"
  if grep -qE "^${key}=" .env 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

[[ -f .env ]] || cp .env.example .env

set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL http://localhost:8000
set_env DB_CONNECTION pgsql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 5432
set_env DB_DATABASE siget_qa
set_env DB_USERNAME siget
set_env DB_PASSWORD siget_qa_password
set_env CACHE_STORE file
set_env SESSION_DRIVER file
set_env QUEUE_CONNECTION sync
set_env MAIL_MAILER log

mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

composer install --no-interaction --prefer-dist --optimize-autoloader

if ! grep -qE '^APP_KEY=base64:.+' .env; then
  php artisan key:generate --force
fi

php artisan optimize:clear
php artisan storage:link 2>/dev/null || true
php artisan migrate --force || echo "ADVERTENCIA: las migraciones no terminaron; revise la configuración o los datos existentes."

if [[ -f package-lock.json ]]; then
  npm ci
elif [[ -f package.json ]]; then
  npm install
fi

if [[ -f package.json ]]; then
  npm run build
fi

echo "=== Entorno SIGET preparado ==="
