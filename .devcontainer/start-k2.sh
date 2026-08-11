#!/usr/bin/env bash

set -u

PGDATA="${SIGET_PGDATA:-/workspaces/qa_render/.codespace/postgres-data}"
PGHOST="127.0.0.1"
PGPORT="5432"
PGUSER="${DB_USERNAME:-siget}"
PGDATABASE="${DB_DATABASE:-siget_qa}"
PGPASSWORD="${DB_PASSWORD:-${SIGET_PGPASSWORD:-siget_codespace_qa}}"
export PGPASSWORD

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
echo "===== POSTGRESQL ====="

if ! command -v initdb >/dev/null 2>&1; then
    echo "ERROR: initdb no está instalado."
    exit 1
fi

if ! command -v postgres >/dev/null 2>&1; then
    echo "ERROR: postgres no está instalado."
    exit 1
fi

mkdir -p "${PGDATA}"

if [ ! -f "${PGDATA}/PG_VERSION" ]; then
    echo "Inicializando PostgreSQL en ${PGDATA}..."
    rm -rf "${PGDATA:?}"/*
    initdb -D "${PGDATA}" --auth-local=trust --auth-host=scram-sha-256 --username="${PGUSER}"
fi

if ! pg_isready -h "${PGHOST}" -p "${PGPORT}" >/dev/null 2>&1; then
    echo "Iniciando PostgreSQL en ${PGHOST}:${PGPORT}..."
    postgres -D "${PGDATA}" \
        -h "${PGHOST}" \
        -p "${PGPORT}" \
        >"${PGDATA}/postgres.log" 2>&1 &
fi

for i in $(seq 1 30); do
    if pg_isready -h "${PGHOST}" -p "${PGPORT}" >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

if ! pg_isready -h "${PGHOST}" -p "${PGPORT}" >/dev/null 2>&1; then
    echo "ERROR: PostgreSQL no inició en ${PGHOST}:${PGPORT}."
    echo "===== POSTGRES LOG ====="
    tail -n 100 "${PGDATA}/postgres.log" 2>/dev/null || true
    exit 1
fi

echo "PostgreSQL: $(pg_isready -h "${PGHOST}" -p "${PGPORT}")"

if ! psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname='${PGUSER}'" 2>/dev/null | grep -q 1; then
    psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -c "CREATE ROLE ${PGUSER} LOGIN PASSWORD '${PGPASSWORD}';"
else
    psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -c "ALTER ROLE ${PGUSER} WITH LOGIN PASSWORD '${PGPASSWORD}';" >/dev/null
fi

if ! psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='${PGDATABASE}'" | grep -q 1; then
    echo "Creando base de datos ${PGDATABASE}..."
    createdb -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -O "${PGUSER}" "${PGDATABASE}"
fi

echo
echo "===== ARTISAN ====="

if [ -f artisan ]; then
    php artisan optimize:clear || true
    php artisan --version
else
    echo "artisan no encontrado"
fi

echo
echo "===== SIGET K2 LISTO ====="
echo
echo "PostgreSQL: http://127.0.0.1:5432"
echo "Laravel: http://localhost:8000"
echo "Mailpit: http://localhost:8025"
echo
echo "Para levantar SIGET manualmente:"
echo "php artisan serve --host=0.0.0.0 --port=8000"
