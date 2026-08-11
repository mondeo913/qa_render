#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail(){ echo "ERROR: $*" >&2; exit 1; }
ok(){ echo "OK: $*"; }

printf '\n===== SIGET K2: VERIFICAR / COMPILAR / EJECUTAR =====\n'

echo "=== 1. AMBIENTE ==="
[ "${CODESPACES_RECOVERY_CONTAINER:-false}" != "true" ] || fail "Codespace está en RECOVERY. No se puede validar el ambiente real."
command -v php >/dev/null || fail "PHP no está instalado"
command -v composer >/dev/null || fail "Composer no está instalado"
command -v node >/dev/null || fail "Node no está instalado"
command -v npm >/dev/null || fail "NPM no está instalado"
command -v psql >/dev/null || fail "PostgreSQL client no está instalado"
command -v supervisord >/dev/null || fail "Supervisor no está instalado"
command -v mailpit >/dev/null || fail "Mailpit no está instalado"
ok "Herramientas del ambiente disponibles"
php -v | head -1
composer --version
node -v
npm -v
psql --version
supervisord --version
mailpit version

[ -f artisan ] || fail "No existe artisan"
[ -f composer.json ] || fail "No existe composer.json"
[ -f package.json ] || fail "No existe package.json"
ok "Archivos Laravel presentes"

 echo "=== 2. DEPENDENCIAS PHP ==="
composer validate --no-check-publish
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan --version
ok "Laravel/Composer preparados"

echo "=== 3. DEPENDENCIAS NODE Y BUILD ==="
npm install
npm run build
ok "Vite compiló correctamente"

echo "=== 4. CONFIGURACION LARAVEL ==="
if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi
if [ -f .env ]; then php artisan key:generate --force >/dev/null 2>&1 || true; fi
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:list >/tmp/siget-routes.txt
ok "Laravel puede cargar configuración y rutas"

echo "=== 5. ARRANQUE SIGET ==="
if pgrep -f 'php artisan serve.*--port=8000' >/dev/null 2>&1; then
  pkill -f 'php artisan serve.*--port=8000' || true
  sleep 1
fi
nohup php artisan serve --host=0.0.0.0 --port=8000 > /tmp/siget-k2.log 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" 2>/dev/null || true' EXIT
sleep 3

if ! kill -0 "$SERVER_PID" 2>/dev/null; then
  cat /tmp/siget-k2.log >&2 || true
  fail "SIGET no pudo iniciar en el puerto 8000"
fi

if command -v curl >/dev/null 2>&1; then
  HTTP_CODE="$(curl -sS -o /tmp/siget-k2-response.html -w '%{http_code}' --max-time 10 http://127.0.0.1:8000/ || true)"
  echo "HTTP / => ${HTTP_CODE}"
  case "$HTTP_CODE" in 2*|3*) ok "SIGET responde por HTTP" ;; *) cat /tmp/siget-k2.log >&2 || true; fail "SIGET respondió con HTTP ${HTTP_CODE}" ;; esac
else
  echo "AVISO: curl no disponible; se verificó únicamente el proceso PHP."
fi

echo "=== 6. RESULTADO FINAL ==="
ok "SIGET K2 AMBIENTE + DEPENDENCIAS + BUILD + ARRANQUE: OK"
echo "SIGET: http://localhost:8000"
echo "Mailpit: http://localhost:8025"
