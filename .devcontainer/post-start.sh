#!/usr/bin/env bash
set -Eeuo pipefail

cd /workspaces/qa_render
service postgresql start >/dev/null 2>&1 || true

if [[ -f artisan && -f composer.json && -f ARRANCAR_SIGET.sh ]]; then
  bash ARRANCAR_SIGET.sh --rapido || true
else
  echo "Codespace listo. Falta cargar el código completo de SIGET para arrancarlo."
fi
