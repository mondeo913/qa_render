#!/usr/bin/env bash

set +e

echo "======================================"
echo " SIGET K2 - BOOTSTRAP CODESPACES"
echo "======================================"

echo ""
echo "===== PHP ====="
php -v || true

echo ""
echo "===== COMPOSER ====="
composer --version || true

echo ""
echo "===== NODE ====="
node -v || true

echo ""
echo "===== NPM ====="
npm -v || true

echo ""
echo "===== POSTGRES CLIENT ====="
psql --version || true

echo ""
echo "===== SUPERVISOR ====="
supervisord --version || true

echo ""
echo "===== SIGET ====="

if [ -f artisan ]; then
    php artisan --version || true
else
    echo "WARN: artisan no encontrado"
fi

echo ""
echo "======================================"
echo " BOOTSTRAP FINALIZADO"
echo "======================================"

exit 0
