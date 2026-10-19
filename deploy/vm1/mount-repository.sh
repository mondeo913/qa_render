#!/usr/bin/env bash
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo 'Ejecute con sudo en VM1.' >&2; exit 1; }
vm2_ip="${1:?Uso: sudo bash deploy/vm1/mount-repository.sh IP_PRIVADA_VM2}"
python3 - "$vm2_ip" <<'PY'
import ipaddress,sys
ip=ipaddress.ip_address(sys.argv[1])
if ip.version!=4 or not ip.is_private or ip.is_loopback: raise SystemExit('Indique una IPv4 privada de VM2.')
PY
apt-get update
apt-get install -y nfs-common
install -d -m 0750 /srv/siget/repository
mount_line="$vm2_ip:/srv/siget/repository /srv/siget/repository nfs4 rw,hard,vers=4.2,proto=tcp,_netdev,nosuid,nodev,noexec 0 0"
if rg -q '^[^#]+[[:space:]]/srv/siget/repository[[:space:]]' /etc/fstab 2>/dev/null ||
   grep -Eq '^[^#]+[[:space:]]/srv/siget/repository[[:space:]]' /etc/fstab; then
  echo 'Ya existe una entrada para el repositorio en fstab; se conserva. Verifique que corresponda a VM2.'
else
  printf '%s\n' "$mount_line" >> /etc/fstab
fi
systemctl daemon-reload
mountpoint -q /srv/siget/repository || mount /srv/siget/repository
[[ $(findmnt -n -o FSTYPE --target /srv/siget/repository) == nfs4 ]] || { echo 'No hay un montaje NFSv4 válido.' >&2; exit 1; }
[[ $(findmnt -n -o SOURCE --target /srv/siget/repository) == "$vm2_ip:/srv/siget/repository" ]] || { echo 'El montaje existente no corresponde a la VM2 indicada.' >&2; exit 1; }
setpriv --reuid=33 --regid=33 --clear-groups test -r /srv/siget/repository/.siget-repository-id || { echo 'Falta el identificador de VM2 o los permisos UID/GID 33.' >&2; exit 1; }
echo 'VM2 montada. No se utiliza un directorio local como sustituto del repositorio.'
