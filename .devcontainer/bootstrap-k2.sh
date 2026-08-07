#!/usr/bin/env bash
set -u

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="${PROJECT_ROOT}/.codespace/logs"
mkdir -p "${LOG_DIR}"
LOG_FILE="${LOG_DIR}/bootstrap-k2.log"
exec > >(tee -a "${LOG_FILE}") 2>&1

warn() { echo "ADVERTENCIA: $*" >&2; }
step() { echo; echo "=== $* ==="; }

step "Preparando herramientas base de SIGET K2"

if command -v sudo >/dev/null 2>&1; then
  SUDO=sudo
else
  SUDO=""
fi

if ! ${SUDO} apt-get update; then
  warn "apt-get update falló. El Codespace seguirá abierto para diagnóstico."
fi

${SUDO} apt-get install -y --no-install-recommends \
  ca-certificates curl git jq \
  libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev libpng-dev \
  libpq-dev libsqlite3-dev libxml2-dev libzip-dev \
  postgresql postgresql-client sqlite3 procps supervisor unzip zip \
  || warn "No fue posible instalar uno o más paquetes del sistema."

step "Verificando PHP"
php -v || warn "PHP no está disponible en la imagen base."

if command -v php >/dev/null 2>&1 && command -v docker-php-ext-install >/dev/null 2>&1; then
  missing=()
  for ext in bcmath dom intl mbstring pdo_pgsql pdo_sqlite pgsql xml xmlreader xmlwriter zip; do
    php -m | grep -Fxqi "$ext" || missing+=("$ext")
  done

  if ((${#missing[@]})); then
    echo "Instalando extensiones PHP faltantes: ${missing[*]}"
    ${SUDO} docker-php-ext-install -j2 "${missing[@]}" \
      || warn "Alguna extensión PHP no pudo compilarse."
  fi

  if ! php -m | grep -Fxqi gd; then
    ${SUDO} docker-php-ext-configure gd --with-freetype --with-jpeg \
      && ${SUDO} docker-php-ext-install -j2 gd \
      || warn "La extensión gd no pudo compilarse."
  fi
fi

step "Verificando Composer"
if ! command -v composer >/dev/null 2>&1; then
  EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig 2>/dev/null || true)"
  if curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
      && [ -n "${EXPECTED_CHECKSUM}" ] \
      && [ "$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');" 2>/dev/null)" = "${EXPECTED_CHECKSUM}" ]; then
    ${SUDO} php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
      || warn "No fue posible instalar Composer."
  else
    warn "No fue posible descargar o verificar el instalador de Composer."
  fi
  rm -f /tmp/composer-setup.php
fi
composer --version || warn "Composer no está disponible."

step "Verificando Node.js y NPM"
node --version || warn "Node.js no está disponible; revise la feature de Node del devcontainer."
npm --version || warn "NPM no está disponible; revise la feature de Node del devcontainer."

step "Instalando Mailpit si hace falta"
if ! command -v mailpit >/dev/null 2>&1; then
  arch="$(dpkg --print-architecture 2>/dev/null || uname -m)"
  case "$arch" in
    amd64|x86_64) mailpit_arch="amd64" ;;
    arm64|aarch64) mailpit_arch="arm64" ;;
    *) mailpit_arch="" ;;
  esac

  if [ -n "$mailpit_arch" ]; then
    if curl -fL --retry 4 --retry-all-errors \
      "https://github.com/axllent/mailpit/releases/latest/download/mailpit-linux-${mailpit_arch}.tar.gz" \
      -o /tmp/mailpit.tar.gz; then
      ${SUDO} tar -xzf /tmp/mailpit.tar.gz -C /usr/local/bin mailpit \
        && ${SUDO} chmod 0755 /usr/local/bin/mailpit \
        || warn "No fue posible instalar Mailpit."
    else
      warn "No fue posible descargar Mailpit."
    fi
    rm -f /tmp/mailpit.tar.gz
  else
    warn "Arquitectura no reconocida para Mailpit: $arch"
  fi
fi
mailpit version || warn "Mailpit no está disponible."

step "Comprobación mínima del entorno"
for cmd in php composer node npm psql pg_isready supervisord curl; do
  if command -v "$cmd" >/dev/null 2>&1; then
    echo "OK - $cmd"
  else
    warn "FALTA - $cmd"
  fi
done

step "Instalación de SIGET K2"
if [ -x "${PROJECT_ROOT}/.devcontainer/install-siget.sh" ] || [ -f "${PROJECT_ROOT}/.devcontainer/install-siget.sh" ]; then
  bash "${PROJECT_ROOT}/.devcontainer/install-siget.sh" \
    || warn "La aplicación no terminó de instalarse. El Codespace permanecerá operativo. Ejecute después: bash .devcontainer/install-siget.sh"
else
  warn "No se encontró .devcontainer/install-siget.sh"
fi

echo
echo "Bootstrap K2 finalizado."
echo "Log: ${LOG_FILE}"
exit 0
