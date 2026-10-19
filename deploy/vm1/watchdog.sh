#!/usr/bin/env bash
set -euo pipefail
source "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"
exec 9>/run/lock/siget-operations.lock
flock -n 9 || exit 0
check_repository
if [[ -f /var/lib/siget/infrastructure-maintenance ]] &&
   [[ $(cat /var/lib/siget/infrastructure-maintenance) == backup ]]; then
  operation_state="$(docker inspect --format '{{.State.Running}}' siget-monthly-operation 2>/dev/null || true)"
  if [[ "$operation_state" == true ]]; then
    # El cliente de Compose puede morir mientras el contenedor de respaldo continúa.
    # No se permite reactivar escritores hasta que esa operación haya terminado.
    exit 0
  fi
fi
compose up -d --no-build
for service in database antivirus app; do
  container_id="$(compose ps -q "$service")"
  [[ -n "$container_id" ]] || { echo "Falta el servicio $service" >&2; exit 1; }
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id")"
  if [[ "$state" == unhealthy ]]; then
    logger -p daemon.warning -t siget "Reiniciando servicio no saludable: $service"
    compose restart "$service"
  fi
done
if [[ -f /var/lib/siget/infrastructure-maintenance ]] &&
   [[ $(cat /var/lib/siget/infrastructure-maintenance) == backup ]]; then
  compose exec -T app php deploy/fpm-health.php
  compose exec -T app php artisan up
  rm -f /var/lib/siget/infrastructure-maintenance
  logger -p daemon.warning -t siget 'Servicio recuperado después de un respaldo interrumpido.'
fi
