#!/usr/bin/env bash
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo 'Ejecute sudo bash deploy/vm1/install.sh' >&2; exit 1; }
exec python3 "$(dirname -- "${BASH_SOURCE[0]}")/wizard.py" "$@"
