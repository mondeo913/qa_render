#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")"

echo "== SIGET K1: certificación del tablero Kanban =="
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder --force
php artisan route:list --name=loads.board
php artisan test --filter='LoadBoard|RoleMenu|AccessScopeIsolation'
npm run build

echo "K1 completado. Verifique manualmente el tablero con los roles operativo, director y enlace institucional."
