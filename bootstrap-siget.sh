#!/usr/bin/env bash
set -u
ROOT="${CODESPACE_VSCODE_FOLDER:-/workspaces/$(basename "${PWD}")}"
[ -d "$ROOT" ] || ROOT="$PWD"
cd "$ROOT" || exit 0
mkdir -p .codespace/logs
LOG=".codespace/logs/bootstrap.log"
exec > >(tee -a "$LOG") 2>&1

echo "===== SIGET K2 WIZARD BOOTSTRAP $(date) ====="
missing=0
for c in php composer node npm psql pg_isready supervisord mailpit; do
  if command -v "$c" >/dev/null 2>&1; then echo "OK   $c"; else echo "MISS $c"; missing=1; fi
done

if [ "$missing" -ne 0 ]; then
  echo "Herramientas incompletas. No se aborta la creación del Codespace."
  exit 0
fi

if [ -f .env.example ] && [ ! -f .env ]; then cp .env.example .env; fi

if [ -f artisan ]; then
  [ -d vendor ] || composer install --no-interaction --prefer-dist --no-progress || true
  if [ -f .env ] && grep -q '^APP_KEY=$' .env 2>/dev/null; then
    php artisan key:generate --force || true
  fi
  mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
  chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true
  php artisan config:clear || true
  php artisan cache:clear || true
  php artisan view:clear || true
fi

if [ -f package-lock.json ]; then npm ci || npm install || true
elif [ -f package.json ]; then npm install || true
fi

echo "===== BOOTSTRAP FINALIZADO ====="
exit 0
