#!/usr/bin/env bash

set -u

echo "=============================================="
echo " SIGET K2 - INICIO"
echo "=============================================="

echo
echo "===== VALIDACION AMBIENTE ====="

php -v
composer --version
node -v
npm -v
psql --version
supervisord --version
mailpit version

echo
echo "===== ARTISAN ====="

if [ -f artisan ]; then
    php artisan --version
else
    echo "artisan no encontrado"
fi

echo
echo "===== SIGET K2 LISTO ====="
echo
echo "Laravel: http://localhost:8000"
echo "Mailpit: http://localhost:8025"
echo

if [ -f artisan ]; then
    echo "Para levantar SIGET manualmente:"
    echo "php artisan serve --host=0.0.0.0 --port=8000"
fi
