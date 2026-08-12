#!/usr/bin/env bash

set -u

PGDATA="${SIGET_PGDATA:-/workspaces/qa_render/.codespace/postgres-data}"
PGHOST="127.0.0.1"
PGPORT="5432"
PGUSER="${DB_USERNAME:-siget}"
PGDATABASE="${DB_DATABASE:-siget_qa}"
PGPASSWORD="${DB_PASSWORD:-${SIGET_PGPASSWORD:-siget_codespace_qa}}"
export PGPASSWORD

PG_BINDIR=""
if command -v pg_config >/dev/null 2>&1; then
    PG_BINDIR="$(pg_config --bindir 2>/dev/null || true)"
fi
if [ -z "${PG_BINDIR}" ] || [ ! -x "${PG_BINDIR}/initdb" ] || [ ! -x "${PG_BINDIR}/postgres" ]; then
    PG_BINDIR="$(find /usr/lib/postgresql -mindepth 2 -maxdepth 2 -type f -name initdb -executable -printf '%h\n' 2>/dev/null | sort -V | tail -n 1)"
fi

INITDB="${PG_BINDIR}/initdb"
POSTGRES="${PG_BINDIR}/postgres"
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

# Bootstrap over the trusted Unix socket before using TCP/SCRAM.
if ! psql -h "${PGSOCKET}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname='${PGUSER}'" 2>/dev/null | grep -q 1; then
    echo "Creando usuario PostgreSQL ${PGUSER}..."
    psql -h "${PGSOCKET}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -c "CREATE ROLE ${PGUSER} LOGIN PASSWORD '${PGPASSWORD}';"
else
    psql -h "${PGSOCKET}" -p "${PGPORT}" -U "${PGUSER}" -d postgres -c "ALTER ROLE ${PGUSER} WITH LOGIN PASSWORD '${PGPASSWORD}';" >/dev/null
fi

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
    echo "Aplicando migraciones de Laravel..."
    if ! php artisan migrate --force; then
        echo "ERROR: las migraciones de Laravel fallaron."
        exit 1
    fi

    echo "Sincronizando catálogo base QA..."
    if ! php artisan db:seed --class=RolePermissionSeeder --force; then
        echo "ERROR: no se pudieron crear/sincronizar roles y permisos base."
        exit 1
    fi
    if ! php artisan db:seed --class=AgencyTemplateSeeder --force; then
        echo "ERROR: no se pudo crear/sincronizar la dependencia y unidades QA."
        exit 1
    fi

    echo "Sincronizando usuarios QA..."
    if ! php artisan siget:qa-users; then
        echo "ERROR: no se pudieron crear/sincronizar los usuarios QA."
        exit 1
    fi

    echo "Limpiando cachés de Laravel..."
    if ! php artisan optimize:clear; then
        echo "ERROR: no se pudieron limpiar las cachés de Laravel."
        exit 1
    fi

    echo "===== FRONTEND / VITE ====="
    if [ ! -f package.json ]; then
        echo "ERROR: package.json no encontrado; no se puede construir la interfaz."
        exit 1
    fi
    if [ ! -f package-lock.json ]; then
        echo "ERROR: package-lock.json no encontrado; no se puede instalar el frontend de forma reproducible."
        exit 1
    fi
    if [ ! -d node_modules ]; then
        echo "Instalando dependencias npm..."
        if ! npm ci; then
            echo "ERROR: npm ci falló."
            exit 1
        fi
    fi
    echo "Construyendo assets de producción con Vite..."
    if ! npm run build; then
        echo "ERROR: npm run build falló; la interfaz SIGET no puede publicarse."
        exit 1
    fi
    if [ ! -f public/build/manifest.json ]; then
        echo "ERROR: Vite terminó sin generar public/build/manifest.json."
        exit 1
    fi
    echo "Frontend: BUILD OK (public/build/manifest.json)"

    php artisan --version

    echo "===== LARAVEL / PUERTO 8000 ====="
    mkdir -p .codespace

    # Remove a stale Laravel process/PID so the forwarded port always points
    # to the current workspace and not to an old process from a previous wake-up.
    if [ -f .codespace/laravel.pid ]; then
        OLD_PID="$(cat .codespace/laravel.pid 2>/dev/null || true)"
        if [ -n "${OLD_PID}" ] && kill -0 "${OLD_PID}" 2>/dev/null; then
            echo "Deteniendo Laravel anterior (PID ${OLD_PID})..."
            kill "${OLD_PID}" 2>/dev/null || true
            sleep 1
        fi
    fi

    # Kill only listeners owned by this workspace user on 8000.
    if command -v fuser >/dev/null 2>&1; then
        fuser -k 8000/tcp 2>/dev/null || true
    fi

    rm -f .codespace/laravel.pid
    echo "Iniciando Laravel en 0.0.0.0:8000..."
    nohup php artisan serve --host=0.0.0.0 --port=8000 > .codespace/laravel.log 2>&1 &
    LARAVEL_PID=$!
    echo "${LARAVEL_PID}" > .codespace/laravel.pid

    READY=0
    for i in $(seq 1 30); do
        if curl -fsS http://127.0.0.1:8000/iniciar-sesion >/dev/null 2>&1; then
            READY=1
            break
        fi
        sleep 1
    done

    if [ "${READY}" -eq 1 ]; then
        echo "Laravel: ACTIVO en http://0.0.0.0:8000"
        echo "Port 8000: configurado para auto-forward + abrir navegador + público"
    else
        echo "ERROR: Laravel no respondió en 8000."
        echo "===== LARAVEL LOG ====="
        tail -n 100 .codespace/laravel.log 2>/dev/null || true
        exit 1
    fi
else
    echo "artisan no encontrado"
fi

echo
echo "===== SIGET K2 LISTO ====="
echo
echo "PostgreSQL: 127.0.0.1:5432 (activo)"
echo "Base de datos: ${PGDATABASE} (migrada)"
echo "Laravel: http://localhost:8000"
echo "Login: http://localhost:8000/iniciar-sesion"
echo "Mailpit: http://localhost:8025"
echo
echo "Usuarios QA sincronizados:"
echo "  admin@siget.local"
echo "  director.general@siget.local"
echo "  director@siget.local"
echo "  director.produccion@siget.local"
echo "  enlace@siget.local"
echo "  operador.monitoreo@siget.local"
echo "  operador.produccion@siget.local"
echo "  fiscalizador@siget.local"
echo
echo "Password QA: ${SIGET_QA_PASSWORD:-SigetQA_2026_Cambiar!}"