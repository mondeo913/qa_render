#!/usr/bin/env bash
set -euo pipefail

cd /workspaces/qa_render

echo "=== ACTUALIZANDO PAQUETES ==="
sudo apt-get update

sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
  php8.3-cli \
  php8.3-common \
  php8.3-mbstring \
  php8.3-xml \
  php8.3-curl \
  php8.3-zip \
  php8.3-gd \
  php8.3-intl \
  php8.3-pgsql \
  php8.3-sqlite3 \
  unzip \
  curl \
  git

if ! command -v composer >/dev/null 2>&1; then
  EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
  if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo "ERROR: Firma de Composer inválida"
    rm -f composer-setup.php
    exit 1
  fi
  sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f composer-setup.php
fi

if ! command -v node >/dev/null 2>&1 || ! node --version | grep -q '^v22\.'; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs
fi

echo "=== VERSIONES ==="
php --version
composer --version
node --version
npm --version

if [ ! -f artisan ] || [ ! -f composer.json ]; then
  echo
  echo "ERROR: PHP ya quedó instalado, pero SIGET no está cargado en esta rama."
  echo "Faltan artisan y/o composer.json en /workspaces/qa_render."
  echo "Contenido actual:"
  ls -la
  exit 2
fi

composer install --no-interaction --prefer-dist --optimize-autoloader

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

php artisan key:generate --force
php artisan optimize:clear

if [ -f package-lock.json ]; then
  npm ci
elif [ -f package.json ]; then
  npm install
fi

if [ -f package.json ]; then
  npm run build
fi

php artisan about
php artisan route:list --except-vendor

echo "=== INICIANDO SIGET EN PUERTO 8000 ==="
exec php artisan serve --host=0.0.0.0 --port=8000
