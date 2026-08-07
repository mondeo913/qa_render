#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib-siget.sh"

stop_supervisor

if postgres_is_ready; then
  pg_bin="$(find_postgres_bin)"
  "${pg_bin}/pg_ctl" -D "${PGDATA}" stop -m fast || true
fi

echo "SIGET, Mailpit, cola, scheduler y PostgreSQL fueron detenidos."
