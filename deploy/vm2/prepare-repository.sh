#!/usr/bin/env bash
set -euo pipefail
umask 0027
[[ $EUID -eq 0 ]] || { echo 'Ejecute con sudo en VM2.' >&2; exit 1; }
vm1_ip="${1:?Uso: sudo bash deploy/vm2/prepare-repository.sh IP_PRIVADA_VM1}"
python3 - "$vm1_ip" <<'PY'
import ipaddress,sys
ip=ipaddress.ip_address(sys.argv[1])
if ip.version!=4 or not ip.is_private or ip.is_loopback: raise SystemExit('Indique una IPv4 privada de VM1.')
PY
apt-get update
apt-get install -y nfs-kernel-server
install -d -o 33 -g 33 -m 0750 /srv/siget/repository
if [[ ! -f /srv/siget/repository/.siget-repository-id ]]; then
  cat /proc/sys/kernel/random/uuid > /srv/siget/repository/.siget-repository-id
  chown 33:33 /srv/siget/repository/.siget-repository-id
  chmod 0640 /srv/siget/repository/.siget-repository-id
fi
install -d -m 0755 /etc/exports.d /etc/nfs.conf.d
printf '/srv/siget/repository %s(rw,sync,no_subtree_check,root_squash)\n' "$vm1_ip" > /etc/exports.d/siget.exports
cat > /etc/nfs.conf.d/siget.conf <<'CONF'
[nfsd]
vers2=n
vers3=n
vers4=y
udp=n
tcp=y
CONF
exportfs -ra
systemctl enable --now nfs-server
systemctl restart nfs-server
echo 'Repositorio privado preparado. Permita TCP 2049 únicamente desde VM1 en el firewall institucional.'
echo 'Identificador del repositorio:'
cat /srv/siget/repository/.siget-repository-id
