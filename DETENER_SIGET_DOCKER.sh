#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")"
docker compose down
echo "SIGET QA detenido. Los datos de PostgreSQL se conservaron."
