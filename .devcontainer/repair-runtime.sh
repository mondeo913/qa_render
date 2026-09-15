#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="/workspaces/qa_render"
PGDATA="${SIGET_PGDATA:-${PROJECT_ROOT}/.codespace/postgres-data}"
PGPORT="${SIGET_PGPORT:-5432}"

cd "${PROJECT_ROOT}"
mkdir -p "${PROJECT_ROOT}/.codespace"

# Codespaces can preserve the PostgreSQL data directory while the old
# postmaster process disappears. In that case postmaster.pid blocks startup.
# Remove the PID only when PostgreSQL is definitely not running.
if [[ -f "${PGDATA}/PG_VERSION" && -f "${PGDATA}/postmaster.pid" ]]; then
  if ! pg_isready -h 127.0.0.1 -p "${PGPORT}" >/dev/null 2>&1; then
    PG_CTL=""
    if command -v pg_ctl >/dev/null 2>&1; then
      PG_CTL="$(command -v pg_ctl)"
    elif command -v pg_config >/dev/null 2>&1; then
      PG_BIN="$(pg_config --bindir 2>/dev/null || true)"
      [[ -x "${PG_BIN}/pg_ctl" ]] && PG_CTL="${PG_BIN}/pg_ctl"
    fi

    RUNNING=0
    if [[ -n "${PG_CTL}" ]]; then
      if "${PG_CTL}" -D "${PGDATA}" status >/dev/null 2>&1; then
        RUNNING=1
      fi
    fi

    if [[ "${RUNNING}" -eq 0 ]]; then
      echo "Detectado postmaster.pid obsoleto; eliminándolo para permitir el arranque de PostgreSQL."
      rm -f "${PGDATA}/postmaster.pid"
    else
      echo "PostgreSQL tiene un proceso activo; no se eliminará postmaster.pid."
    fi
  fi
fi

exec bash .devcontainer/start-k2.sh
