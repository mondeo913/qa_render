#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${ROOT}"

echo "============================================================"
echo " SIGET K2 QA - RECUPERAR SERVICIOS"
echo "============================================================"

source "${ROOT}/.devcontainer/lib-siget.sh"
ensure_directories

echo
echo "1) Verificando PostgreSQL..."
if postgres_is_ready; then
  echo "OK PostgreSQL ya está disponible en 127.0.0.1:${PGPORT}"
else
  echo "PostgreSQL no responde; ejecutando recuperación segura..."

  if [ -f "$PGDATA/postmaster.pid" ]; then
    pid="$(head -n 1 "$PGDATA/postmaster.pid" 2>/dev/null || true)"
    stale=1

    if [[ "$pid" =~ ^[0-9]+$ ]] && kill -0 "$pid" >/dev/null 2>&1; then
      cmd="$(ps -p "$pid" -o args= 2>/dev/null || true)"
      if [[ "$cmd" == *postgres* ]] && [[ "$cmd" == *"$PGDATA"* ]]; then
        stale=0
        echo "PostgreSQL tiene un proceso activo (PID $pid); no se tocará el PID."
      fi
    fi

    if [ "$stale" -eq 1 ]; then
      echo "Eliminando postmaster.pid obsoleto..."
      rm -f "$PGDATA/postmaster.pid"
    fi
  fi

  if [ -d "$PGDATA" ]; then
    chmod 700 "$PGDATA" >/dev/null 2>&1 || true
    chown -R "$(id -u):$(id -g)" "$PGDATA" >/dev/null 2>&1 || true
  fi

  if command -v ss >/dev/null 2>&1 && ss -ltn "sport = :$PGPORT" | grep -q ":$PGPORT"; then
    echo "ERROR: el puerto $PGPORT ya está ocupado por otro proceso." >&2
    ss -ltnp "sport = :$PGPORT" 2>/dev/null || true
    exit 20
  fi

  if ! start_postgres; then
    echo >&2
    echo "ERROR: PostgreSQL no pudo iniciar." >&2
    echo "Últimas líneas de $LOG_DIR/postgres.log:" >&2
    tail -n 100 "$LOG_DIR/postgres.log" 2>/dev/null || true
    exit 21
  fi
fi

echo
echo "2) Verificando Supervisor y servicios SIGET..."
if ! supervisor_is_running; then
  start_supervisor
else
  supervisorctl -c "${SUPERVISOR_CONFIG}" reread >/dev/null 2>&1 || true
  supervisorctl -c "${SUPERVISOR_CONFIG}" update >/dev/null 2>&1 || true
  for program in siget-web siget-queue siget-scheduler siget-mailpit siget-postgres-watchdog; do
    if ! supervisorctl -c "${SUPERVISOR_CONFIG}" status "${program}" 2>/dev/null | grep -q "RUNNING"; then
      supervisorctl -c "${SUPERVISOR_CONFIG}" start "${program}" >/dev/null 2>&1 || true
    fi
  done
fi

echo
echo "3) Verificando aplicación..."
if ! curl -fsS --max-time 20 "http://127.0.0.1:8000/up" >/dev/null 2>&1; then
  echo "La aplicación web no responde; reiniciando SIGET..."
  supervisorctl -c "${SUPERVISOR_CONFIG}" restart siget-web >/dev/null 2>&1 || true
fi

wait_for_http "http://127.0.0.1:8000/up" "SIGET" 60

echo
show_service_status
echo

WATCHDOG_SCRIPT="${ROOT}/.devcontainer/siget-watchdog.sh"
WATCHDOG_LOG="${LOG_DIR}/siget-watchdog-launcher.log"
if [[ -f "${WATCHDOG_SCRIPT}" ]]; then
  chmod +x "${WATCHDOG_SCRIPT}" >/dev/null 2>&1 || true
  nohup bash "${WATCHDOG_SCRIPT}" >> "${WATCHDOG_LOG}" 2>&1 &
  echo "Watchdog SIGET activo en segundo plano."
fi

echo "SIGET disponible: $(app_url)/iniciar-sesion"
echo "La base persistente NO se borra ni se reinicializa durante esta recuperación."