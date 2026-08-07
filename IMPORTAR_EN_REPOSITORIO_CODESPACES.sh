#!/usr/bin/env bash
set -Eeuo pipefail

SOURCE_DIR="$(cd "$(dirname "$0")" && pwd)"
TARGET_DIR="${CODESPACE_VSCODE_FOLDER:-$(pwd)}"

echo "Origen:  ${SOURCE_DIR}"
echo "Destino: ${TARGET_DIR}"

if [[ ! -d "${TARGET_DIR}/.git" ]]; then
  echo "El directorio destino no parece ser un repositorio Git." >&2
  echo "Abra este comando desde la raíz del repositorio del Codespace." >&2
  exit 10
fi

if [[ "${SOURCE_DIR}" == "${TARGET_DIR}" ]]; then
  echo "El paquete ya está en la raíz del repositorio."
else
  tar \
    --exclude='.git' \
    --exclude='INVENTARIO.json' \
    --exclude='SHA256SUMS.txt' \
    -C "${SOURCE_DIR}" \
    -cf - . |
  tar -C "${TARGET_DIR}" -xf -
fi

find "${TARGET_DIR}" -maxdepth 1 \
  -type f \
  -name 'SIGET_ABCD_CODESPACES_QA_AUTOMATICO_V2_0*.zip' \
  -delete || true

if [[ "${SOURCE_DIR}" != "${TARGET_DIR}" &&
      "${SOURCE_DIR}" == "${TARGET_DIR}/"* ]]; then
  rm -rf "${SOURCE_DIR}"
fi

cd "${TARGET_DIR}"

chmod +x \
  .devcontainer/*.sh \
  ./*.sh

if [[ -z "$(git config --get user.name || true)" ]]; then
  git config user.name "${GITHUB_USER:-Usuario Codespaces}"
fi

if [[ -z "$(git config --get user.email || true)" ]]; then
  git config user.email "${GITHUB_USER:-codespaces-user}@users.noreply.github.com"
fi

git add .

if git diff --cached --quiet; then
  echo "No hay cambios nuevos para guardar."
else
  git commit -m "Instalar SIGET K2 QA para Codespaces"
  git push
fi

echo
echo "============================================================"
echo " ARCHIVOS IMPORTADOS"
echo "============================================================"
echo
echo "Ahora ejecuta en Codespaces:"
echo
echo "1. Presiona Ctrl+Shift+P"
echo "2. Selecciona: Codespaces: Rebuild Container"
echo "3. Espera la instalación automática"
echo
