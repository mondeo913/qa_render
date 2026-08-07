#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# SIGET K2 - Instalación y levantamiento en GitHub Codespaces
# Sistema de Gestión de Evidencias de Transmisión
#
# Uso recomendado:
#   1) Si el ZIP ya fue descomprimido en la raíz del repositorio:
#        bash INSTALAR_Y_LEVANTAR_SIGET_K2.sh
#
#   2) Si únicamente subiste el ZIP a la raíz del repositorio:
#        bash INSTALAR_Y_LEVANTAR_SIGET_K2.sh \
#          --zip SIGET_K2_CODESPACES_INSTALACION_RAIZ.zip
#      Después, si el script lo solicita, ejecuta
#      "Codespaces: Rebuild Container" y vuelve a correrlo.
#
# Opciones:
#   --zip RUTA       Descomprime el paquete K2 si aún no está instalado.
#   --certificar     Ejecuta K2_CERTIFICAR_COMPLETO.sh al final.
#   --solo-iniciar   No reinstala dependencias; solo levanta servicios.
#   --ayuda          Muestra ayuda.
# ============================================================

SCRIPT_VERSION="1.0"
ZIP_PATH=""
RUN_CERTIFICATION=0
START_ONLY=0

print_help() {
  cat <<'HELP'
SIGET K2 - Instalación y levantamiento en GitHub Codespaces

Uso:
  bash INSTALAR_Y_LEVANTAR_SIGET_K2.sh [opciones]

Opciones:
  --zip RUTA       Descomprime SIGET_K2_CODESPACES_INSTALACION_RAIZ.zip
                   en la raíz actual si todavía no existen artisan y
                   .devcontainer/.
  --certificar     Ejecuta la certificación completa K2 después de levantar.
  --solo-iniciar   Levanta una instalación existente sin reinstalar paquetes.
  --ayuda          Muestra esta ayuda.

Ejemplos:
  bash INSTALAR_Y_LEVANTAR_SIGET_K2.sh
  bash INSTALAR_Y_LEVANTAR_SIGET_K2.sh --certificar
  bash INSTALAR_Y_LEVANTAR_SIGET_K2.sh --zip SIGET_K2_CODESPACES_INSTALACION_RAIZ.zip
HELP
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --zip)
      [[ $# -ge 2 ]] || { echo "ERROR: --zip requiere una ruta." >&2; exit 2; }
      ZIP_PATH="$2"
      shift 2
      ;;
    --certificar)
      RUN_CERTIFICATION=1
      shift
      ;;
    --solo-iniciar)
      START_ONLY=1
      shift
      ;;
    --ayuda|-h|--help)
      print_help
      exit 0
      ;;
    *)
      echo "ERROR: opción desconocida: $1" >&2
      print_help
      exit 2
      ;;
  esac
done

log()  { printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }
warn() { printf '\nADVERTENCIA: %s\n' "$*" >&2; }
die()  { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

on_error() {
  local code=$?
  echo
  echo "============================================================"
  echo " SIGET K2 - LA EJECUCIÓN TERMINÓ CON ERROR (${code})"
  echo "============================================================"
  if [[ -d .codespace/logs ]]; then
    echo "Revise los registros en: $(pwd)/.codespace/logs"
    for f in .codespace/logs/install.log .codespace/logs/web-error.log \
             .codespace/logs/queue-error.log .codespace/logs/scheduler-error.log \
             storage/logs/laravel.log; do
      [[ -f "$f" ]] && echo "  - $f"
    done
  fi
  echo
  echo "Para diagnóstico adicional, ejecute:"
  echo "  bash DIAGNOSTICAR_SIGET.sh"
  exit "$code"
}
trap on_error ERR

# La raíz objetivo es la carpeta desde la cual el usuario ejecuta el script.
PROJECT_ROOT="$(pwd)"
cd "$PROJECT_ROOT"

log "SIGET K2 - instalador/arrancador v${SCRIPT_VERSION}"
echo "Directorio de trabajo: ${PROJECT_ROOT}"

# ------------------------------------------------------------
# 1. Descomprimir el ZIP, si se proporcionó y el proyecto aún
#    no está desplegado en la raíz.
# ------------------------------------------------------------
if [[ -n "$ZIP_PATH" ]]; then
  [[ -f "$ZIP_PATH" ]] || die "No se encontró el ZIP: $ZIP_PATH"

  if [[ -f artisan && -d .devcontainer ]]; then
    warn "Ya existen artisan y .devcontainer/. No se descomprimirá el ZIP para evitar sobrescrituras accidentales."
  else
    command -v unzip >/dev/null 2>&1 || die "No está disponible 'unzip'."
    log "Descomprimiendo ${ZIP_PATH} en ${PROJECT_ROOT}..."
    unzip -o "$ZIP_PATH" -d "$PROJECT_ROOT"
  fi
fi

# ------------------------------------------------------------
# 2. Verificar que estamos en la raíz de SIGET K2.
# ------------------------------------------------------------
REQUIRED_PROJECT_FILES=(
  artisan
  composer.json
  package.json
  .devcontainer/devcontainer.json
  .devcontainer/install-siget.sh
  .devcontainer/start-siget.sh
  .devcontainer/lib-siget.sh
  K2_CERTIFICAR_COMPLETO.sh
)

MISSING_PROJECT_FILES=()
for f in "${REQUIRED_PROJECT_FILES[@]}"; do
  [[ -e "$f" ]] || MISSING_PROJECT_FILES+=("$f")
done

if [[ ${#MISSING_PROJECT_FILES[@]} -gt 0 ]]; then
  echo "Faltan archivos esenciales del proyecto:" >&2
  printf '  - %s\n' "${MISSING_PROJECT_FILES[@]}" >&2
  die "Ejecute este script desde la raíz donde se descomprimió SIGET_K2_CODESPACES_INSTALACION_RAIZ.zip."
fi

chmod +x \
  .devcontainer/install-siget.sh \
  .devcontainer/start-siget.sh \
  .devcontainer/verify-siget.sh \
  .devcontainer/diagnose-siget.sh \
  K2_CERTIFICAR_COMPLETO.sh \
  INICIAR_SIGET.sh \
  DIAGNOSTICAR_SIGET.sh \
  MOSTRAR_URLS.sh 2>/dev/null || true

# ------------------------------------------------------------
# 3. Comprobar si el contenedor actual es el devcontainer K2.
#    El Dockerfile del ZIP instala estas herramientas. Si faltan,
#    no intentamos improvisar una instalación distinta.
# ------------------------------------------------------------
REQUIRED_COMMANDS=(php composer node npm psql pg_isready supervisord mailpit curl)
MISSING_COMMANDS=()
for cmd in "${REQUIRED_COMMANDS[@]}"; do
  command -v "$cmd" >/dev/null 2>&1 || MISSING_COMMANDS+=("$cmd")
done

if [[ ${#MISSING_COMMANDS[@]} -gt 0 ]]; then
  echo
  echo "Faltan herramientas del devcontainer K2: ${MISSING_COMMANDS[*]}"
  echo
  echo "El código ya está preparado, pero este Codespace debe reconstruirse"
  echo "con .devcontainer/Dockerfile para instalar PHP, PostgreSQL, Mailpit,"
  echo "Supervisor y las extensiones requeridas."
  echo
  echo "EN VS CODE / CODESPACES:"
  echo "  1. Ctrl+Shift+P"
  echo "  2. Seleccione: Codespaces: Rebuild Container"
  echo "     (o Dev Containers: Rebuild Container)"
  echo "  3. Espere a que finalice."
  echo "  4. Vuelva a ejecutar:"
  echo "       bash $(basename "$0")${RUN_CERTIFICATION:+ --certificar}"
  exit 20
fi

# Validar extensiones antes de iniciar la instalación Laravel.
source .devcontainer/lib-siget.sh
if ! assert_php_extensions; then
  echo
  echo "El contenedor actual no contiene todas las extensiones PHP de SIGET K2."
  echo "Ejecute 'Codespaces: Rebuild Container' y vuelva a correr este script."
  exit 21
fi

# ------------------------------------------------------------
# 4. Instalar/actualizar o solamente levantar.
# ------------------------------------------------------------
if [[ "$START_ONLY" -eq 1 ]]; then
  log "Modo --solo-iniciar: levantando servicios existentes..."
  bash .devcontainer/start-siget.sh
else
  log "Instalando/actualizando SIGET K2..."
  echo "Esta operación NO ejecuta migrate:fresh si la base QA ya fue inicializada."
  bash .devcontainer/install-siget.sh

  log "Asegurando que todos los servicios permanezcan levantados..."
  bash .devcontainer/start-siget.sh
fi

# ------------------------------------------------------------
# 5. Verificación funcional básica.
# ------------------------------------------------------------
log "Verificando servicios..."
bash .devcontainer/verify-siget.sh

# Mostrar estado de Supervisor y PostgreSQL.
show_service_status

# ------------------------------------------------------------
# 6. Certificación completa opcional.
# ------------------------------------------------------------
if [[ "$RUN_CERTIFICATION" -eq 1 ]]; then
  log "Ejecutando certificación completa K2..."
  bash K2_CERTIFICAR_COMPLETO.sh
fi

# ------------------------------------------------------------
# 7. Resultado y accesos.
# ------------------------------------------------------------
APP_URL_VALUE="$(app_url)"
MAILPIT_URL_VALUE="$(mailpit_url)"

cat <<EOF

============================================================
 SIGET K2 - INSTALACIÓN Y LEVANTAMIENTO COMPLETADOS
============================================================

SIGET:
  ${APP_URL_VALUE}/iniciar-sesion

Correo QA / Mailpit:
  ${MAILPIT_URL_VALUE}

Usuario administrador QA:
  admin@siget.local

Archivo local con accesos QA:
  ${STATE_DIR}/ACCESOS.txt

Comandos útiles:
  bash MOSTRAR_URLS.sh
  bash INICIAR_SIGET.sh
  bash DIAGNOSTICAR_SIGET.sh
  bash K2_CERTIFICAR_COMPLETO.sh

Logs principales:
  ${LOG_DIR}/install.log
  ${LOG_DIR}/web.log
  ${LOG_DIR}/web-error.log
  ${LOG_DIR}/queue.log
  ${LOG_DIR}/scheduler.log
  storage/logs/laravel.log

Puertos Codespaces:
  8000  SIGET
  8025  Mailpit

IMPORTANTE:
  Mantenga ambos puertos en visibilidad PRIVATE durante QA salvo que
  Seguridad institucional autorice otra configuración.

============================================================

EOF
