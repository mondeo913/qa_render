#!/usr/bin/env bash

set -u

PGDATA="${SIGET_PGDATA:-/workspaces/qa_render/.codespace/postgres-data}"
PGHOST="127.0.0.1"
PGPORT="5432"
PGUSER="${DB_USERNAME:-siget}"
PGDATABASE="${DB_DATABASE:-siget_qa}"
PGPASSWORD="${DB_PASSWORD:-${SIGET_PGPASSWORD:-siget_codespace_qa}}"
export PGPASSWORD

# Debian installs PostgreSQL server binaries under /usr/lib/postgresql/<version>/bin.
PG_BINDIR=""
if command -v pg_config >/dev/null 2>&1; then
    PG_BINDIR="$(pg_config --bindir 2>/dev/null || true)"
fi
if [ -z "${PG_BINDIR}" ] || [ ! -x "${PG_BINDIR}/initdb" ] || [ ! -x "${PG_BINDIR}/postgres" ]; then
    PG_BINDIR="$(find /usr/lib/postgresql -mindepth 2 -maxdepth 2 -type f -name initdb -executable -printf '%h\n' 2>/dev/null | sort -V | tail -n 1)"
fi

INITDB="${PG_BINDIR}/initdb"
POSTGRES="${PG_BINDIR}/postgres"

# Use the PostgreSQL data directory for the Unix socket. This avoids the
# non-writable /var/run/postgresql path in a non-root Codespace container.
PGSOCKET="${PGDATA}"

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
echo "PostgreSQL binario: ${PG_BINDIR:-NO ENCONTRADO}"

if [ ! -x "${INITDB}" ]; then
    echo "ERROR: initdb no está disponible."
    echo "Buscado en: ${INITDB}"
    exit 1
fi

if [ ! -x "${POSTGRES}" ]; then
    echo "ERROR: postgres no está disponible."
    echo "Buscado en: ${POSTGRES}"
    exit 1
fi

mkdir -p "${PGDATA}"

if [ ! -f "${PGDATA}/PG_VERSION" ]; then
    echo "Inicializando PostgreSQL en ${PGDATA}..."
    rm -rf "${PGDATA:?}"/*
    "${INITDB}" -D "${PGDATA}" --auth-local=trust --auth-host=scram-sha-256 --username="${PGUSER}"
fi

if ! pg_isready -h "${PGHOST}" -p "${PGPORT}" >/dev/null 2>&1; then
    echo "Iniciando PostgreSQL en ${PGHOST}:${PGPORT}..."
    "${POSTGRES}" -D "${PGDATA}" \
        -h "${PGHOST}" \
        -p "${PGPORT}" \
        -k "${PGSOCKET}" \
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

# Bootstrap over the trusted Unix socket first. The TCP connection uses
# SCRAM authentication, so the password must be established before using
# -h 127.0.0.1. This fixes fresh clusters where the role has no password yet.
if ! psql -h "${PGSOCKET}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname='${PGUSER}'" 2>/dev/null | grep -q 1; then
    echo "Creando usuario PostgreSQL ${PGUSER}..."
    psql -h "${PGSOCKET}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -c "CREATE ROLE ${PGUSER} LOGIN PASSWORD '${PGPASSWORD}';"
else
    psql -h "${PGSOCKET}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -c "ALTER ROLE ${PGUSER} WITH LOGIN PASSWORD '${PGPASSWORD}';" >/dev/null
fi

# Verify the actual TCP credentials Laravel will use.
if ! PGPASSWORD="${PGPASSWORD}" psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -tAc "SELECT 1" >/dev/null 2>&1; then
    echo "ERROR: las credenciales TCP de PostgreSQL no son válidas para ${PGUSER}."
    exit 1
fi

if ! PGPASSWORD="${PGPASSWORD}" psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='${PGDATABASE}'" | grep -q 1; then
    echo "Creando base de datos ${PGDATABASE}..."
    PGPASSWORD="${PGPASSWORD}" createdb -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -O "${PGUSER}" "${PGDATABASE}"
fi

echo "Base de datos: ${PGDATABASE} - OK"

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
echo "PostgreSQL: 127.0.0.1:5432 (activo)"
echo "Laravel: http://localhost:8000"
echo "Mailpit: http://localhost:8025"
echo
echo "Para levantar SIGET manualmente:"
echo "php artisan serve --host=0.0.0.0 --port=8000"
