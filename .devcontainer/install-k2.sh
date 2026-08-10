#!/usr/bin/env bash

set -u

echo "=============================================="
echo " SIGET K2 - INSTALACION DEL AMBIENTE"
echo "=============================================="

echo
echo "===== VERSIONES DEL AMBIENTE ====="

php -v || true
composer --version || true
node -v || true
npm -v || true
psql --version || true
supervisord --version || true
mailpit version || true

echo
echo "===== VALIDANDO SIGET ====="

if [ -f artisan ]; then
    echo "OK - artisan encontrado"
else
    echo "AVISO - artisan no encontrado"
fi

if [ -f composer.json ]; then
    echo "OK - composer.json encontrado"
else
    echo "AVISO - composer.json no encontrado"
fi

echo
echo "===== INSTALANDO DEPENDENCIAS PHP ====="

if [ -f composer.json ]; then
    composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader
else
    echo "composer.json no encontrado; se omite composer install"
fi

echo
echo "===== INSTALANDO DEPENDENCIAS NODE ====="

if [ -f package.json ]; then
    npm install
else
    echo "package.json no encontrado; se omite npm install"
fi

echo
echo "===== CONFIGURACION LARAVEL ====="

if [ -f .env.example ] && [ ! -f .env ]; then
    cp .env.example .env
    echo "OK - .env creado"
fi

if [ -f artisan ]; then
    php artisan key:generate --force || true
fi

echo
echo "=============================================="
echo " SIGET K2 - INSTALACION FINALIZADA"
echo "=============================================="
