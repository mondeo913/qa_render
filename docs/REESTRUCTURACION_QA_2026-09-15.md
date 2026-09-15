# Limpieza y consolidación QA SIGET — 2026-09-15

La línea QA se consolidó sobre el árbol funcional vigente y se eliminaron respaldos, paquetes históricos, instaladores sustituidos y utilidades duplicadas.

La ruta vigente del ambiente es:

`devcontainer.json → Dockerfile.k2 → install-siget.sh → repair-runtime.sh → qa-runtime.sh`

La certificación integral se ejecuta con `K2_CERTIFICAR_COMPLETO.sh`.
