#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
STATE_DIR="${PROJECT_ROOT}/.codespace"
LOG_DIR="${STATE_DIR}/logs"
PGDATA="${STATE_DIR}/postgres-data"
PGSOCKET="${STATE_DIR}/postgres-socket"
PGPORT="${SIGET_PGPORT:-5432}"
PGUSER="${SIGET_PGUSER:-siget}"
PGPASSWORD="${SIGET_PGPASSWORD:-siget_codespace_qa}"
PGDATABASE="${SIGET_PGDATABASE:-siget_qa}"
SUPERVISOR_CONFIG="${STATE_DIR}/supervisord.conf"
SUPERVISOR_SOCKET="${STATE_DIR}/supervisor.sock"
SUPERVISOR_PID="${STATE_DIR}/supervisord.pid"

export PGPASSWORD

assert_php_extensions() {
  local required_extensions=(
    bcmath
    dom
    gd
    intl
    mbstring
    pdo_pgsql
    pdo_sqlite
    pgsql
    xml
    xmlreader
    xmlwriter
    zip
  )
  local missing_extensions=()
  local extension

  for extension in "${required_extensions[@]}"; do
    if ! php -m | grep -Fxqi "${extension}"; then
      missing_extensions+=("${extension}")
    fi
  done

  if [[ ${#missing_extensions[@]} -gt 0 ]]; then
    echo "Faltan extensiones PHP requeridas: ${missing_extensions[*]}" >&2
    echo "Ejecute: Codespaces: Rebuild Container" >&2
    return 1
  fi
}

codespace_url() {
  local port="$1"

  if [[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]]; then
    printf 'https://%s-%s.%s' \
      "${CODESPACE_NAME}" \
      "${port}" \
      "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
  else
    printf 'http://localhost:%s' "${port}"
  fi
}

app_url() {
  codespace_url 8000
}

mailpit_url() {
  codespace_url 8025
}

ensure_directories() {
  mkdir -p \
    "${STATE_DIR}" \
    "${LOG_DIR}" \
    "${PGSOCKET}" \
    "${PROJECT_ROOT}/storage/app/public" \
    "${PROJECT_ROOT}/storage/framework/cache/data" \
    "${PROJECT_ROOT}/storage/framework/sessions" \
    "${PROJECT_ROOT}/storage/framework/views" \
    "${PROJECT_ROOT}/storage/logs" \
    "${PROJECT_ROOT}/bootstrap/cache" \
    "${PROJECT_ROOT}/RESPALDOS"

  chmod -R u+rwX \
    "${STATE_DIR}" \
    "${PROJECT_ROOT}/storage" \
    "${PROJECT_ROOT}/bootstrap/cache" \
    "${PROJECT_ROOT}/RESPALDOS"
}

find_postgres_bin() {
  local initdb_path

  initdb_path="$(
    command -v initdb 2>/dev/null || \
    find /usr/libexec/postgresql* /usr/lib/postgresql* \
      -maxdepth 3 \
      -type f \
      -name initdb 2>/dev/null |
    sort -V |
    tail -n 1
  )"

  if [[ -z "${initdb_path}" ]]; then
    echo "No se encontró initdb de PostgreSQL." >&2
    return 1
  fi

  dirname "${initdb_path}"
}

postgres_is_ready() {
  pg_isready \
    -h 127.0.0.1 \
    -p "${PGPORT}" \
    -U "${PGUSER}" \
    -d "${PGDATABASE}" >/dev/null 2>&1
}

initialize_postgres() {
  local pg_bin password_file
  pg_bin="$(find_postgres_bin)"
  ensure_directories

  if [[ ! -f "${PGDATA}/PG_VERSION" ]]; then
    echo "Inicializando PostgreSQL persistente..."

    rm -rf "${PGDATA}"
    mkdir -p "${PGDATA}"

    password_file="${STATE_DIR}/postgres-password.tmp"
    printf '%s' "${PGPASSWORD}" > "${password_file}"
    chmod 600 "${password_file}"

    "${pg_bin}/initdb" \
      -D "${PGDATA}" \
      -U "${PGUSER}" \
      --pwfile="${password_file}" \
      --auth-local=trust \
      --auth-host=scram-sha-256 \
      --encoding=UTF8 \
      --locale=C.UTF-8

    rm -f "${password_file}"

    cat >> "${PGDATA}/postgresql.conf" <<EOF
listen_addresses = '127.0.0.1'
port = ${PGPORT}
unix_socket_directories = '${PGSOCKET}'
max_connections = 100
shared_buffers = 128MB
timezone = 'America/Mexico_City'
EOF
  fi
}

start_postgres() {
  local pg_bin
  pg_bin="$(find_postgres_bin)"

  initialize_postgres

  if ! postgres_is_ready; then
    echo "Iniciando PostgreSQL..."

    "${pg_bin}/pg_ctl" \
      -D "${PGDATA}" \
      -l "${LOG_DIR}/postgres.log" \
      -o "-h 127.0.0.1 -p ${PGPORT} -k ${PGSOCKET}" \
      start
  fi

  for _ in $(seq 1 90); do
    if pg_isready \
        -h 127.0.0.1 \
        -p "${PGPORT}" \
        -U "${PGUSER}" >/dev/null 2>&1; then
      break
    fi

    sleep 1
  done

  if ! pg_isready \
      -h 127.0.0.1 \
      -p "${PGPORT}" \
      -U "${PGUSER}" >/dev/null 2>&1; then
    echo "PostgreSQL no inició. Revise ${LOG_DIR}/postgres.log" >&2
    return 1
  fi

  if ! psql \
      -h 127.0.0.1 \
      -p "${PGPORT}" \
      -U "${PGUSER}" \
      -d postgres \
      -tAc "SELECT 1 FROM pg_database WHERE datname='${PGDATABASE}'" |
      grep -q 1; then
    createdb \
      -h 127.0.0.1 \
      -p "${PGPORT}" \
      -U "${PGUSER}" \
      "${PGDATABASE}"
  fi
}

update_env_urls() {
  local app_url_value mailpit_url_value
  app_url_value="$(app_url)"
  mailpit_url_value="$(mailpit_url)"

  sed -i "s|^APP_URL=.*|APP_URL=${app_url_value}|" "${PROJECT_ROOT}/.env"
  sed -i "s|^SIGET_MAILPIT_URL=.*|SIGET_MAILPIT_URL=${mailpit_url_value}|" "${PROJECT_ROOT}/.env"
}

write_supervisor_config() {
  ensure_directories

  cat > "${SUPERVISOR_CONFIG}" <<EOF
[unix_http_server]
file=${SUPERVISOR_SOCKET}
chmod=0700

[supervisord]
logfile=${LOG_DIR}/supervisord.log
pidfile=${SUPERVISOR_PID}
childlogdir=${LOG_DIR}
nodaemon=false

[rpcinterface:supervisor]
supervisor.rpcinterface_factory=supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix://${SUPERVISOR_SOCKET}

[program:siget-web]
directory=${PROJECT_ROOT}
command=/usr/local/bin/php artisan serve --host=0.0.0.0 --port=8000
autostart=true
autorestart=true
startsecs=2
startretries=5
stopasgroup=true
killasgroup=true
stdout_logfile=${LOG_DIR}/web.log
stderr_logfile=${LOG_DIR}/web-error.log

[program:siget-queue]
directory=${PROJECT_ROOT}
command=/usr/local/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=1200 --max-time=3600
autostart=true
autorestart=true
startsecs=2
startretries=5
stopasgroup=true
killasgroup=true
stdout_logfile=${LOG_DIR}/queue.log
stderr_logfile=${LOG_DIR}/queue-error.log

[program:siget-scheduler]
directory=${PROJECT_ROOT}
command=/usr/local/bin/php artisan schedule:work
autostart=true
autorestart=true
startsecs=2
startretries=5
stopasgroup=true
killasgroup=true
stdout_logfile=${LOG_DIR}/scheduler.log
stderr_logfile=${LOG_DIR}/scheduler-error.log

[program:siget-mailpit]
directory=${PROJECT_ROOT}
command=/usr/local/bin/mailpit --listen 0.0.0.0:8025 --smtp 127.0.0.1:1025 --database ${STATE_DIR}/mailpit.db --disable-version-check
autostart=true
autorestart=true
startsecs=2
startretries=5
stopasgroup=true
killasgroup=true
stdout_logfile=${LOG_DIR}/mailpit.log
stderr_logfile=${LOG_DIR}/mailpit-error.log
EOF
}

supervisor_is_running() {
  [[ -f "${SUPERVISOR_PID}" ]] &&
    kill -0 "$(cat "${SUPERVISOR_PID}")" >/dev/null 2>&1
}

start_supervisor() {
  write_supervisor_config

  if supervisor_is_running; then
    supervisorctl -c "${SUPERVISOR_CONFIG}" reread >/dev/null || true
    supervisorctl -c "${SUPERVISOR_CONFIG}" update >/dev/null || true
    supervisorctl -c "${SUPERVISOR_CONFIG}" restart all >/dev/null || true
  else
    rm -f "${SUPERVISOR_SOCKET}" "${SUPERVISOR_PID}"
    supervisord -c "${SUPERVISOR_CONFIG}"
  fi
}

stop_supervisor() {
  if supervisor_is_running; then
    supervisorctl -c "${SUPERVISOR_CONFIG}" shutdown || true
  fi
}

wait_for_http() {
  local url="$1"
  local name="$2"
  local attempts="${3:-120}"

  for _ in $(seq 1 "${attempts}"); do
    if curl -fsS \
        --max-time 10 \
        "${url}" >/dev/null 2>&1; then
      return 0
    fi

    sleep 1
  done

  echo "${name} no respondió en ${url}" >&2
  return 1
}

show_service_status() {
  echo
  echo "ESTADO DE SERVICIOS"
  echo "-------------------"

  if supervisor_is_running; then
    supervisorctl -c "${SUPERVISOR_CONFIG}" status || true
  else
    echo "Supervisor no está iniciado."
  fi

  echo
  pg_isready \
    -h 127.0.0.1 \
    -p "${PGPORT}" \
    -U "${PGUSER}" \
    -d "${PGDATABASE}" || true
}
