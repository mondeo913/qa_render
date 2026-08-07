#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")"

REPORT_DIR="storage/app/qa/k2-certificacion"
mkdir -p "$REPORT_DIR"
LOG="$REPORT_DIR/certificacion-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1

echo "=== SIGET K2: certificación visual y funcional ==="
echo "Inicio: $(date -Is)"

php -r 'foreach(["dom","mbstring","xml","xmlwriter","pdo_pgsql"] as $e){if(!extension_loaded($e)){fwrite(STDERR,"Falta extensión PHP: $e\n");exit(2);}} echo "Extensiones PHP requeridas: OK\n";'

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder --force

php artisan route:list --name=loads.board
php artisan route:list --name=review.inbox
php artisan route:list --name=password
php artisan route:list --name=alerts

php artisan test

npm ci
npm run build

php artisan view:cache
php artisan config:cache
php artisan route:cache
php artisan siget:qa-status || true

echo "Fin: $(date -Is)"
echo "K2_CERTIFICACION_OK"
echo "Reporte: $LOG"
