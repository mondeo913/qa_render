#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

ensure_directories

INSTALL_LOG="${LOG_DIR}/install.log"
exec > >(tee -a "${INSTALL_LOG}") 2>&1

cd "${PROJECT_ROOT}"

echo "============================================================"
echo " SIGET K2 QA - INSTALACIÓN AUTOMÁTICA EN CODESPACES"
echo "============================================================"

REQUIRED_COMMANDS=(
  php
  composer
  node
  npm
  psql
  pg_isready
  supervisord
  mailpit
  curl
)

MISSING_COMMANDS=()

for command_name in "${REQUIRED_COMMANDS[@]}"; do
  if ! command -v "${command_name}" >/dev/null 2>&1; then
    MISSING_COMMANDS+=("${command_name}")
  fi
done

if [[ ${#MISSING_COMMANDS[@]} -gt 0 ]]; then
  echo "Faltan herramientas: ${MISSING_COMMANDS[*]}" >&2
  echo "Ejecute: Codespaces: Rebuild Container" >&2
  exit 10
fi

APP_URL_VALUE="$(app_url)"
MAILPIT_URL_VALUE="$(mailpit_url)"

echo "0/10 Extensiones PHP..."
assert_php_extensions

echo "1/10 PostgreSQL..."
start_postgres

echo "2/10 Configuración .env..."

if [[ ! -f .env ]]; then
  cat > .env <<EOF
APP_NAME="SIGET K2 QA Codespaces"
APP_ENV=qa
APP_KEY=
APP_DEBUG=true
APP_URL=${APP_URL_VALUE}
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_TIMEZONE=America/Mexico_City
SIGET_NAME="SIGET"
SIGET_SUBTITLE="Sistema de Gestión de Evidencias de Transmisión"
SIGET_ENVIRONMENT_LABEL="QA CODESPACES"
SIGET_DEFAULT_THEME=auto

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=${PGPORT}
DB_DATABASE=${PGDATABASE}
DB_USERNAME=${PGUSER}
DB_PASSWORD=${PGPASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=240
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
SIGET_REPOSITORY_DISK=local
SIGET_ANTIVIRUS_ENABLED=false

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="siget-qa@localhost"
MAIL_FROM_NAME="SIGET K2 QA"
SIGET_MAILPIT_URL=${MAILPIT_URL_VALUE}
SIGET_ACCOUNTING_RECIPIENTS=contabilidad@siget.local

SIGET_ADMIN_EMAIL=admin@siget.local
SIGET_QA_PASSWORD=SigetQA_2026_Cambiar!
SIGET_METRICS_TOKEN=codespaces_metrics_qa_v2
EOF
else
  update_env_urls
fi

echo "3/10 Dependencias PHP..."

for attempt in 1 2 3; do
  if composer install \
      --no-interaction \
      --prefer-dist \
      --no-progress \
      --optimize-autoloader; then
    break
  fi

  if [[ "${attempt}" -eq 3 ]]; then
    echo "Composer falló después de tres intentos." >&2
    exit 20
  fi

  echo "Reintentando Composer en 10 segundos..."
  sleep 10
done

echo "4/10 Dependencias de la interfaz..."

for attempt in 1 2 3; do
  if [[ -f package-lock.json ]]; then
    npm_command=(npm ci --no-audit --no-fund)
  else
    npm_command=(npm install --no-audit --no-fund)
  fi

  if "${npm_command[@]}"; then
    break
  fi

  if [[ "${attempt}" -eq 3 ]]; then
    echo "npm falló después de tres intentos." >&2
    exit 21
  fi

  npm cache clean --force || true
  echo "Reintentando npm en 10 segundos..."
  sleep 10
done

echo "5/10 Clave de Laravel..."

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
  php artisan key:generate --force
fi

echo "6/10 Base de datos y datos ABCD QA..."

if [[ ! -f "${STATE_DIR}/database-initialized" ]]; then
  php artisan migrate:fresh --seed --force
else
  php artisan migrate --force
fi

php artisan siget:qa-init
touch "${STATE_DIR}/database-initialized"

echo "7/10 Almacenamiento y cachés..."

php artisan storage:link >/dev/null 2>&1 || true
php artisan optimize:clear

echo "8/10 Compilando interfaz..."

npm run build

echo "9/10 Iniciando aplicación y servicios..."

start_supervisor

wait_for_http "http://127.0.0.1:8000/up" "SIGET" 180
wait_for_http "http://127.0.0.1:8025/readyz" "Mailpit" 120

echo "10/10 Verificación inicial..."

bash "${PROJECT_ROOT}/.devcontainer/verify-siget.sh"

cat > "${STATE_DIR}/ACCESOS.txt" <<EOF
SIGET K2 QA CODESPACES
========================

SIGET:
${APP_URL_VALUE}/iniciar-sesion

MAILPIT:
${MAILPIT_URL_VALUE}

ADMINISTRADOR:
admin@siget.local

CONTRASEÑA:
SigetQA_2026_Cambiar!

OTROS USUARIOS:
director.general@siget.local
director@siget.local                 # Director de Transmisión
director.produccion@siget.local      # Director de Programación y Continuidad
enlace@siget.local
operador.monitoreo@siget.local       # Operativo de Transmisión
operador.produccion@siget.local      # Operativo de Programación y Continuidad
fiscalizador@siget.local
EOF

touch "${STATE_DIR}/application-installed"

echo
echo "============================================================"
echo " SIGET INSTALADO Y FUNCIONANDO"
echo "============================================================"
echo "SIGET:   ${APP_URL_VALUE}/iniciar-sesion"
echo "Mailpit: ${MAILPIT_URL_VALUE}"
echo "Usuario: admin@siget.local"
echo "Clave:   SigetQA_2026_Cambiar!"
echo "Log:     ${INSTALL_LOG}"
echo "============================================================"
