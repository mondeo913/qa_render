#!/usr/bin/env bash
set -euo pipefail
siget_source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$siget_source_dir"
siget_version="$(tr -d '\r\n' < RELEASE_VERSION)"
[[ "$siget_version" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'Versión inválida.' >&2; exit 1; }
siget_commit="$(git rev-parse HEAD)"
siget_package_name="siget-${siget_version}-${siget_commit:0:12}"
mkdir -p dist
siget_package_tmp="$(mktemp -d "$siget_source_dir/dist/package-XXXXXX")"
trap 'rm -rf -- "$siget_package_tmp"' EXIT
siget_package_root="$siget_package_tmp/$siget_package_name"
mkdir -p "$siget_package_root"
# Only committed source is eligible; runtime data and local secrets never enter this archive.
git archive HEAD | tar -xf - -C "$siget_package_root"
rm -f -- "$siget_package_root/public/storage"
if [[ -f public/build/manifest.json ]]; then cp -a public/build "$siget_package_root/public/build"; fi
if [[ "${1:-}" == --images ]]; then
  docker image inspect "siget-app:$siget_version" "siget-web:$siget_version" "siget-antivirus:$siget_version" postgres:16-bookworm \
    --format '{{json .}}' > "$siget_package_root/IMAGE_MANIFEST.jsonl"
  docker save "siget-app:$siget_version" "siget-web:$siget_version" "siget-antivirus:$siget_version" postgres:16-bookworm \
    | gzip -1 > "$siget_package_root/images.tar.gz"
fi
python3 - "$siget_package_root" "$siget_version" "$siget_commit" <<'PY'
import datetime,json,os,pathlib,sys
p=pathlib.Path(sys.argv[1])
manifest={'version':sys.argv[2],'commit':sys.argv[3],'created_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'api':'v1','backup_cadence':'monthly','virtual_machines':2,'contains_images':(p/'images.tar.gz').exists(),
          'contains_secrets':False,'installation_guide':'docs/ENTREGA_INFRAESTRUCTURA_API.md',
          'openapi':'docs/api/openapi.json','ci_run':os.environ.get('SIGET_CI_RUN_URL'),
          'release_criterion':'Todos los trabajos del workflow SIGET CI, incluido Release acceptance gate, deben completar correctamente para este commit.'}
(p/'RELEASE_MANIFEST.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
PY
(cd "$siget_package_root" && find . -type f ! -name SHA256SUMS -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > SHA256SUMS)
tar -czf "dist/$siget_package_name.tar.gz" -C "$siget_package_tmp" "$siget_package_name"
echo "dist/$siget_package_name.tar.gz"
