#!/usr/bin/env bash
set -euo pipefail
siget_deploy_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
siget_release_dir="$(cd -- "$siget_deploy_dir/../.." && pwd)"
siget_env_file="$siget_deploy_dir/.env.production"
[[ -f "$siget_env_file" ]] || { echo 'Ejecute primero el wizard de instalación.' >&2; exit 1; }
compose() { docker compose --env-file "$siget_env_file" -f "$siget_deploy_dir/compose.yml" "$@"; }
check_repository() {
  [[ $(findmnt -n -o FSTYPE --target /srv/siget/repository) == nfs4 ]] &&
    setpriv --reuid=33 --regid=33 --clear-groups test -r /srv/siget/repository/.siget-repository-id || {
      logger -p daemon.crit -t siget 'Repositorio NFS privado no disponible; se bloquea el arranque.'
      echo 'Repositorio NFS privado no disponible.' >&2; return 1;
    }
}
