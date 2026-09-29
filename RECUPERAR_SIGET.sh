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
  start_postgres
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
echo "SIGET disponible: $(app_url)/iniciar-sesion"
echo "La base persistente NO se borra ni se reinicializa durante esta recuperación."