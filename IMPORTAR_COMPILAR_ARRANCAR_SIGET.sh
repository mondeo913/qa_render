#!/usr/bin/env bash
set -Eeuo pipefail

DESTINO="/workspaces/qa_render"
FUENTE="/tmp/siget-abcd-qa-source"
REPO_FUENTE="evanpugaruiz-cloud/siget-abcd-qa"
RAMA_FUENTE="${SIGET_SOURCE_BRANCH:-main}"
FECHA="$(date +%Y%m%d_%H%M%S)"
RESPALDO="/workspaces/qa_render_antes_importar_${FECHA}.tar.gz"

cd "$DESTINO"

echo "============================================================"
echo " IMPORTAR, COMPILAR Y ARRANCAR SIGET QA"
echo "============================================================"

echo "1/6 Guardando respaldo del contenido actual..."
tar \
  --exclude='./.git' \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./storage/logs/*' \
  -czf "$RESPALDO" .
echo "Respaldo: $RESPALDO"

echo "2/6 Descargando el proyecto completo desde $REPO_FUENTE..."
rm -rf "$FUENTE"
export GIT_LFS_SKIP_SMUDGE=1

if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
  gh repo clone "$REPO_FUENTE" "$FUENTE" -- \
    --depth 1 \
    --branch "$RAMA_FUENTE"
else
  git clone \
    --depth 1 \
    --branch "$RAMA_FUENTE" \
    "https://github.com/${REPO_FUENTE}.git" \
    "$FUENTE"
fi

echo "3/6 Copiando el código sin reemplazar el entorno nuevo de Codespaces..."
tar -C "$FUENTE" \
  --exclude='./.git' \
  --exclude='./.devcontainer' \
  --exclude='./.env' \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./storage/app/private' \
  --exclude='./storage/logs' \
  -cf - . | tar -C "$DESTINO" -xf -

rm -rf "$FUENTE"

for archivo in artisan composer.json package.json package-lock.json vite.config.js; do
  if [[ ! -f "$archivo" ]]; then
    echo "ERROR: después de la importación todavía falta: $archivo" >&2
    exit 20
  fi
done

echo "4/6 Proyecto SIGET importado correctamente."

if [[ ! -x ./ARRANCAR_SIGET.sh ]]; then
  chmod +x ./ARRANCAR_SIGET.sh 2>/dev/null || true
fi

echo "5/6 Preparando el repositorio local..."
git status --short || true

echo "6/6 Instalando dependencias, compilando y arrancando..."
exec bash ./ARRANCAR_SIGET.sh
