#!/usr/bin/env bash
set -Eeuo pipefail
runtime_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
runtime_tmp="$(mktemp -d)"
trap 'rm -rf "$runtime_tmp"' EXIT
mkdir -p "$runtime_tmp/bin"
cat > "$runtime_tmp/bin/php" <<'PHP_STUB'
#!/usr/bin/env bash
set -Eeuo pipefail
printf x >> "$SIGET_TEST_PHP_CALLS"
if [[ "${SIGET_TEST_PHP_MODE:-}" == failure ]]; then exit 7; fi
for extension in bcmath dom gd intl mbstring pdo_pgsql pdo_sqlite pgsql xml xmlreader xmlwriter zip; do
  if [[ "${SIGET_TEST_PHP_MODE:-}" == missing && "$extension" == gd ]]; then continue; fi
  printf '%s\n' "$extension"
done
# Force a slow/multi-write producer to expose early grep -q pipe closure.
for ((line=0; line<2048; line++)); do printf 'additional_module_%s\n' "$line"; done
PHP_STUB
chmod +x "$runtime_tmp/bin/php"
export PATH="$runtime_tmp/bin:$PATH"
export SIGET_TEST_PHP_CALLS="$runtime_tmp/calls"
source "$runtime_root/.devcontainer/lib-siget.sh"
assert_php_extensions
[[ "$(cat "$SIGET_TEST_PHP_CALLS")" == x ]]
export SIGET_TEST_PHP_MODE=missing
if assert_php_extensions > "$runtime_tmp/output" 2>&1; then
  echo 'ERROR: missing gd accepted' >&2; exit 1
fi
grep -F 'gd' "$runtime_tmp/output" >/dev/null
export SIGET_TEST_PHP_MODE=failure
if assert_php_extensions > "$runtime_tmp/output" 2>&1; then
  echo 'ERROR: broken PHP accepted' >&2; exit 1
fi
echo 'OK: extension detection handles all modules, missing modules and PHP failure.'
