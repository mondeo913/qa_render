#!/usr/bin/env bash
set -euo pipefail

cd /workspaces/siget-abcd-qa

echo "[SIGET] Iniciando entorno QA..."

# 1. Instalar dependencias PHP/JS si faltan
if [ ! -d vendor ]; then
  composer install --no-interaction --no-progress
fi

if [ ! -d node_modules ]; then
  npm install --no-audit --no-fund
fi

# 2. Generar assets frontend
npm run build

# 3. Preparar caché y permisos
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear

# 4. Asegurar base de datos y migraciones si aplica
# php artisan migrate --force

# 5. Levantar servidor Laravel
exec php artisan serve --host=0.0.0.0 --port=8000
