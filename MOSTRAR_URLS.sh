#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/.devcontainer/lib-siget.sh"

echo "SIGET:   $(app_url)/iniciar-sesion"
echo "Mailpit: $(mailpit_url)"
echo "Usuario: admin@siget.local"
echo "Clave:   SigetQA_2026_Cambiar!"
