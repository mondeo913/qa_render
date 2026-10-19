#!/usr/bin/env bash
set -euo pipefail
umask 0027
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
exec "$@"
