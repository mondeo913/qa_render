#!/usr/bin/env bash
set -u

cd "$(dirname "$0")/.."
PROJECT_ROOT="$PWD"
STATE_DIR="$PROJECT_ROOT/.codespace"
LOG_DIR="$STATE_DIR/logs"
mkdir -p "$LOG_DIR" "$STATE_DIR"
exec > >(tee -a "$LOG_DIR/bootstrap-codespaces.log") 2>&1

echo "============================================================"
echo " SIGET K2 - BOOTSTRAP CODESPACES"
echo "============================================================"

run_step() {
  local label="$1"
  shift
  echo "[K2] $label"
  if "$@"; then
    echo "[K2] OK - $label"
  else
    echo "[K2] WARN - $label falló; el Codespace continuará disponible."
    return 0
  fi
}

# Mailpit is deliberately installed after the image build so a temporary
# GitHub release/download outage cannot force the entire dev container into recovery.
install_mailpit() {
  if command -v mailpit >/dev/null 2>&1; then
    mailpit version
    return 0
  fi

  local version="v1.30.6"
  local arch url tmp
  arch="$(dpkg --print-architecture 2>/dev/null || echo amd64)"
  case "$arch" in
    amd64) arch="amd64" ;;
    arm64) arch="arm64" ;;
    *) echo "Arquitectura no soportada: $arch"; return 1 ;;
  esac

  url="https://github.com/axllent/mailpit/releases/download/${version}/mailpit-linux-${arch}.tar.gz"
  tmp="/tmp/mailpit.tar.gz"

  curl -fL --retry 5 --retry-all-errors --retry-delay 3 "$url" -o "$tmp"
  sudo tar -xzf "$tmp" -C /usr/local/bin mailpit
  sudo chmod 0755 /usr/local/bin/mailpit
  rm -f "$tmp"
  mailpit version
}

run_step "PHP" php -v
run_step "Composer" composer --version
run_step "Node" node --version
run_step "NPM" npm --version
run_step "PostgreSQL" psql --version
run_step "Supervisor" supervisord --version
run_step "Mailpit" install_mailpit

if [[ ! -f .env && -f .env.example ]]; then
  run_step "Crear .env" cp .env.example .env
fi

if [[ -f composer.json ]]; then
  run_step "Composer install" composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

if [[ -f package-lock.json ]]; then
  run_step "NPM ci" npm ci --no-audit --no-fund
elif [[ -f package.json ]]; then
  run_step "NPM install" npm install --no-audit --no-fund
fi

if [[ -f artisan ]]; then
  run_step "Laravel cache clear" php artisan optimize:clear
  if ! grep -Eq '^APP_KEY=base64:.+' .env 2>/dev/null; then
    run_step "Laravel key generate" php artisan key:generate --force
  fi
fi

# The full SIGET initializer is intentionally non-fatal here. This prevents
# a migration/seeder/network issue from putting the entire Codespace in recovery.
if [[ -x .devcontainer/install-siget.sh ]]; then
  echo "[K2] Ejecutando inicializador SIGET en modo no bloqueante..."
  bash .devcontainer/install-siget.sh || true
fi

echo "============================================================"
echo " K2 BOOTSTRAP TERMINADO - CODESPACE DISPONIBLE"
echo "============================================================"
exit 0
