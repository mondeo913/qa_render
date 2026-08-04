#!/usr/bin/env bash
set -Eeuo pipefail

REPO="/workspaces/qa_render"
cd "$REPO"

if [[ ! -f artisan || ! -f composer.json ]]; then
  echo "ERROR: falta el código de SIGET en $REPO."
  echo "Se requieren, como mínimo: artisan, composer.json, app/, routes/ y database/."
  exit 2
fi

service postgresql start

if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='siget'" | grep -q 1; then
  runuser -u postgres -- psql -c "CREATE ROLE siget LOGIN PASSWORD 'siget_qa_password';"
fi

if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_database WHERE datname='siget_qa'" | grep -q 1; then
  runuser -u postgres -- createdb -O siget siget_qa
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

if [[ ! -f vendor/autoload.php ]]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -qE '^APP_KEY=base64:.+' .env; then
  php artisan key:generate --force
fi

php artisan optimize:clear
php artisan storage:link 2>/dev/null || true
php artisan migrate --force

if [[ "${1:-}" != "--rapido" ]]; then
  if [[ -f package-lock.json ]]; then
    npm ci
  elif [[ -f package.json ]]; then
    npm install
  fi
  [[ -f package.json ]] && npm run build
elif [[ -f package.json && ! -d public/build ]]; then
  [[ -f package-lock.json ]] && npm ci || npm install
  npm run build
fi

bash DETENER_SIGET.sh >/dev/null 2>&1 || true

nohup php artisan serve --host=0.0.0.0 --port=8000 \
  > storage/logs/siget-server.log 2>&1 &
echo $! > storage/framework/siget-server.pid

if php artisan list --raw | grep -qx 'schedule:work'; then
  nohup php artisan schedule:work \
    > storage/logs/siget-scheduler.log 2>&1 &
  echo $! > storage/framework/siget-scheduler.pid
fi

for _ in $(seq 1 45); do
  if curl -fsS http://127.0.0.1:8000/up >/dev/null 2>&1 \
     || curl -fsS http://127.0.0.1:8000/ >/dev/null 2>&1; then
    if [[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]]; then
      URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
    else
      URL="http://localhost:8000"
    fi

    echo
    echo "============================================"
    echo " SIGET QA ESTÁ FUNCIONANDO"
    echo "============================================"
    echo "URL:  $URL"
    echo "Logs: tail -f storage/logs/siget-server.log"
    echo "Parar: bash DETENER_SIGET.sh"
    echo "============================================"
    exit 0
  fi
  sleep 2
done

echo "ERROR: SIGET no respondió en el puerto 8000."
tail -n 120 storage/logs/siget-server.log || true
exit 20
