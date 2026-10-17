#!/usr/bin/env bash
set -euo pipefail
source "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"
exec 9>/run/lock/siget-operations.lock
flock -n 9 || exit 0
check_repository
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
