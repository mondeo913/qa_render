#!/usr/bin/env bash
set -Eeuo pipefail
bash "$(dirname "$0")/.devcontainer/verify-siget.sh"
