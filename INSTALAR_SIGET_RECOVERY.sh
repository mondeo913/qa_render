#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="${PROJECT_ROOT:-/workspaces/qa_render}"
cd "$PROJECT_ROOT"

log(){ printf '\n=== %s ===\n' "$*"; }
fail(){ echo "ERROR: $*" >&2; exit 1; }

if command -v sudo >/dev/null 2>&1; then SUDO=sudo; else SUDO=""; fi

log "Detectando sistema operativo"
if [[ -f /etc/os-release ]]; then
  . /etc/os-release
  echo "${PRETTY_NAME:-${ID:-Linux}}"
fi

install_alpine() {
  log "Instalando herramientas en Alpine recovery"
  $SUDO apk update

  local php_pkg=""
  for candidate in php83 php84 php82; do
    if apk search -x "$candidate" 2>/dev/null | grep -q "^${candidate}-"; then
      php_pkg="$candidate"
      break
    fi
  done
  [[ -n "$php_pkg" ]] || fail "No encontré un paquete PHP compatible en Alpine."
  echo "PHP seleccionado: $php_pkg"

  $SUDO apk add --no-cache \
    bash ca-certificates curl git jq unzip zip procps supervisor \
    nodejs npm postgresql postgresql-client sqlite \
    "$php_pkg" \
    "${php_pkg}-bcmath" "${php_pkg}-ctype" "${php_pkg}-curl" \
    "${php_pkg}-dom" "${php_pkg}-fileinfo" "${php_pkg}-gd" \
    "${php_pkg}-iconv" "${php_pkg}-intl" "${php_pkg}-mbstring" \
    "${php_pkg}-opcache" "${php_pkg}-openssl" "${php_pkg}-pdo" \
    "${php_pkg}-pdo_pgsql" "${php_pkg}-pdo_sqlite" "${php_pkg}-pgsql" \
    "${php_pkg}-phar" "${php_pkg}-session" "${php_pkg}-simplexml" \
    "${php_pkg}-tokenizer" "${php_pkg}-xml" "${php_pkg}-xmlreader" \
    "${php_pkg}-xmlwriter" "${php_pkg}-zip"

  local php_bin
  php_bin="$(command -v "$php_pkg" || true)"
  [[ -n "$php_bin" ]] || php_bin="$(command -v php || true)"
  [[ -n "$php_bin" ]] || fail "PHP se instaló pero no encuentro su ejecutable."
  $SUDO ln -sf "$php_bin" /usr/local/bin/php
  if ! command -v php >/dev/null 2>&1; then
    $SUDO ln -sf "$php_bin" /usr/bin/php
  fi
}

install_debian() {
  log "Instalando herramientas en Debian/Ubuntu recovery"
  $SUDO apt-get update
  $SUDO DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    ca-certificates curl git jq unzip zip procps supervisor \
    nodejs npm postgresql postgresql-client sqlite3 \
    php-cli php-bcmath php-curl php-dom php-gd php-intl php-mbstring \
    php-pgsql php-sqlite3 php-xml php-zip
  local php_bin
  php_bin="$(command -v php || true)"
  [[ -n "$php_bin" ]] || fail "PHP no quedó instalado."
  $SUDO ln -sf "$php_bin" /usr/local/bin/php
}

case "${ID:-}" in
  alpine) install_alpine ;;
  debian|ubuntu) install_debian ;;
  *)
    if command -v apk >/dev/null 2>&1; then install_alpine
    elif command -v apt-get >/dev/null 2>&1; then install_debian
    else fail "Sistema no soportado: no encuentro apk ni apt-get."
    fi
    ;;
esac

log "Instalando Composer"
if ! command -v composer >/dev/null 2>&1; then
  EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]] || fail "La firma del instalador de Composer no coincide."
  $SUDO php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

log "Instalando Mailpit"
if ! command -v mailpit >/dev/null 2>&1; then
  arch="$(uname -m)"
  case "$arch" in
    x86_64|amd64) mailpit_arch="amd64" ;;
    aarch64|arm64) mailpit_arch="arm64" ;;
    *) fail "Arquitectura no soportada para Mailpit: $arch" ;;
  esac
  curl -fL --retry 5 --retry-all-errors \
    "https://github.com/axllent/mailpit/releases/latest/download/mailpit-linux-${mailpit_arch}.tar.gz" \
    -o /tmp/mailpit.tar.gz
  $SUDO tar -xzf /tmp/mailpit.tar.gz -C /usr/local/bin mailpit
  $SUDO chmod 0755 /usr/local/bin/mailpit
  rm -f /tmp/mailpit.tar.gz
fi

log "Verificando herramientas"
missing=0
for cmd in php composer node npm psql pg_isready supervisord mailpit curl; do
  if command -v "$cmd" >/dev/null 2>&1; then
    printf 'OK   %-12s %s\n' "$cmd" "$(command -v "$cmd")"
  else
    printf 'FALTA %-12s\n' "$cmd"
    missing=1
  fi
done
[[ "$missing" -eq 0 ]] || fail "Aún faltan herramientas; no ejecutaré Laravel."

log "Versiones"
php -v | head -n 1
composer --version
node --version
npm --version
psql --version
mailpit version || true

log "Preparando scripts SIGET"
chmod +x .devcontainer/*.sh 2>/dev/null || true
chmod +x ./*.sh 2>/dev/null || true
[[ -f .devcontainer/install-siget.sh ]] || fail "No existe .devcontainer/install-siget.sh"

log "Instalando y levantando SIGET K2"
bash .devcontainer/install-siget.sh

log "Comprobación final"
status="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/up || true)"
echo "SIGET HTTP: $status"
if [[ "$status" == "200" ]]; then
  echo "SIGET K2 quedó funcionando."
else
  echo "SIGET no respondió 200. Revise:"
  echo "  tail -n 100 .codespace/logs/install.log"
  echo "  tail -n 100 .codespace/logs/web-error.log"
  exit 2
fi
