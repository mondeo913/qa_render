#!/usr/bin/env bash
set -euo pipefail
# Exercise the real watchdog control flow without touching Docker, systemd or host state.
siget_source="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
siget_fixture="$(mktemp -d /tmp/siget-watchdog-test-XXXXXX)"
trap 'rm -rf -- "$siget_fixture"' EXIT
mkdir -p "$siget_fixture/bin" "$siget_fixture/state" "$siget_fixture/locks"
export SIGET_TEST_FIXTURE="$siget_fixture"
python3 - "$siget_source/deploy/vm1/watchdog.sh" "$siget_fixture" <<'PY'
from pathlib import Path
import sys
root=Path(sys.argv[2])
for name in ['watchdog.sh','upgrade.sh','start.sh']:
 source=Path(sys.argv[1]).with_name(name).read_text().replace('/var/lib/siget',str(root/'state')).replace('/run/lock',str(root/'locks'))
 (root/'bin'/name).write_text(source)
PY
cat > "$siget_fixture/bin/lib.sh" <<'SH'
check_repository() { :; }
compose() {
  printf 'compose %s\n' "$*" >> "$SIGET_TEST_FIXTURE/calls"
  if [[ "$1" == ps ]]; then printf 'id-%s\n' "${@: -1}"; fi
  if [[ "$*" == 'exec -T app php artisan up' ]]; then return "${SIGET_TEST_UP_RESULT:-0}"; fi
  if [[ "$*" == 'exec -T app php deploy/fpm-health.php' ]]; then return "${SIGET_TEST_PROBE_RESULT:-0}"; fi
  if [[ "$*" == 'run --rm --no-deps -T app php artisan migrate --force' ]]; then return "${SIGET_TEST_MIGRATE_RESULT:-0}"; fi
}
docker() {
  if [[ "${@: -1}" == siget-monthly-operation ]]; then
    printf '%s\n' "${SIGET_TEST_OPERATION_RUNNING:-false}"
  else
    printf '%s\n' "${SIGET_TEST_HEALTH:-healthy}"
  fi
}
logger() { printf 'log %s\n' "$*" >> "$SIGET_TEST_FIXTURE/calls"; }
SH
siget_run_case() {
  : > "$siget_fixture/calls"
  bash "$siget_fixture/bin/watchdog.sh"
}
siget_assert_no_up() {
  if grep -q '^compose up\|artisan up' "$siget_fixture/calls"; then
    echo 'El watchdog reactivó escritores o acceso sin autorización.' >&2; exit 1
  fi
}
# An active lock is authoritative: no service may be started.
(
  exec 8>"$siget_fixture/locks/siget-operations.lock"
  flock 8
  siget_run_case
  siget_assert_no_up
)
# Manual maintenance has no infrastructure marker and must not be cleared.
siget_run_case
grep -Fqx 'compose up -d --no-build' "$siget_fixture/calls"
! grep -q 'artisan up' "$siget_fixture/calls"
# A backup container can survive the loss of its host launcher and lock.
printf 'backup\n' > "$siget_fixture/state/infrastructure-maintenance"
export SIGET_TEST_OPERATION_RUNNING=true
siget_run_case
siget_assert_no_up
test -f "$siget_fixture/state/infrastructure-maintenance"
# Only a completed/absent backup operation may be recovered.
export SIGET_TEST_OPERATION_RUNNING=false
siget_run_case
grep -Fqx 'compose up -d --no-build' "$siget_fixture/calls"
grep -Fqx 'compose exec -T app php artisan up' "$siget_fixture/calls"
test ! -f "$siget_fixture/state/infrastructure-maintenance"
# Readiness must pass before automatic recovery clears maintenance.
printf 'backup\n' > "$siget_fixture/state/infrastructure-maintenance"
export SIGET_TEST_PROBE_RESULT=17
if siget_run_case; then echo 'No debe retirar mantenimiento sin PHP disponible.' >&2; exit 1; fi
test -f "$siget_fixture/state/infrastructure-maintenance"
! grep -q 'artisan up' "$siget_fixture/calls"
unset SIGET_TEST_PROBE_RESULT
siget_run_case
test ! -f "$siget_fixture/state/infrastructure-maintenance"
# Failed recovery retains the marker, exits nonzero and can be retried.
printf 'backup\n' > "$siget_fixture/state/infrastructure-maintenance"
export SIGET_TEST_UP_RESULT=17
if siget_run_case; then echo 'Debe informar el fallo de recuperación.' >&2; exit 1; fi
test -f "$siget_fixture/state/infrastructure-maintenance"
unset SIGET_TEST_UP_RESULT
siget_run_case
test ! -f "$siget_fixture/state/infrastructure-maintenance"
# An unhealthy process is restarted without changing manual maintenance.
export SIGET_TEST_HEALTH=unhealthy
siget_run_case
for service in database antivirus app; do grep -Fqx "compose restart $service" "$siget_fixture/calls"; done
! grep -q 'artisan up' "$siget_fixture/calls"
echo 'WATCHDOG RECOVERY PASS: lock, manual maintenance, live backup, interrupted backup, retry and unhealthy services'
unset SIGET_TEST_HEALTH
: > "$siget_fixture/calls"
export SIGET_TEST_MIGRATE_RESULT=17
if bash "$siget_fixture/bin/upgrade.sh"; then echo 'Una migración fallida debe informar error.' >&2; exit 1; fi
! grep -q 'artisan up' "$siget_fixture/calls"
unset SIGET_TEST_MIGRATE_RESULT
: > "$siget_fixture/calls"
bash "$siget_fixture/bin/upgrade.sh"
grep -Fqx 'compose up -d --no-build --wait --wait-timeout 180' "$siget_fixture/calls"
grep -Fqx 'compose exec -T app php artisan up' "$siget_fixture/calls"
echo 'UPGRADE MAINTENANCE PASS: failed migration remains blocked; healthy update resumes access'
: > "$siget_fixture/calls"
bash "$siget_fixture/bin/start.sh"
grep -Fqx 'compose up -d --no-build --wait --wait-timeout 540' "$siget_fixture/calls"
echo 'START READINESS PASS: system service waits for healthy containers'
