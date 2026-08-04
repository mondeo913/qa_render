#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")"

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: Docker no está disponible en este Codespace." >&2
  echo "Abra el repositorio en un Codespace nuevo usando .devcontainer/devcontainer.json." >&2
  exit 10
fi

if ! docker info >/dev/null 2>&1; then
  echo "ERROR: El motor Docker no está activo o no es accesible por el usuario actual." >&2
  exit 11
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: Docker Compose no está instalado." >&2
  exit 12
fi

if [[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]]; then
  export APP_URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
  export MAILPIT_URL="https://${CODESPACE_NAME}-8025.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
else
  export APP_URL="${APP_URL:-http://localhost:8000}"
  export MAILPIT_URL="${MAILPIT_URL:-http://localhost:8025}"
fi

if [[ "${1:-}" != "--sin-reconstruir" ]]; then
  docker compose build --pull app
fi

docker compose up -d

echo "Esperando a que SIGET responda..."
for i in $(seq 1 90); do
  if curl -fsS http://127.0.0.1:8000/up >/dev/null 2>&1; then
    echo
    echo "============================================================"
    echo " SIGET QA ESTÁ FUNCIONANDO"
    echo "============================================================"
    echo "SIGET:   ${APP_URL}/iniciar-sesion"
    echo "Mailpit: ${MAILPIT_URL}"
    echo "Usuario: admin@siget.local"
    echo "Clave:   SigetQA_2026_Cambiar!"
    echo
    echo "Logs:    docker compose logs -f app"
    echo "Detener: bash DETENER_SIGET_DOCKER.sh"
    echo "============================================================"
    exit 0
  fi
  sleep 2
done

echo "SIGET no respondió. Últimos logs:" >&2
docker compose logs --tail=200 app postgres >&2
exit 20
