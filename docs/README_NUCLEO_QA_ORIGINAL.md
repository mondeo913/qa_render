# SIGET K2 QA para GitHub Codespaces

Edición automática para compilar, ejecutar y probar SIGET sin Fly.io, sin tarjeta y sin permisos de administrador en Windows.

## Servicios incluidos

- Laravel 12 y PHP 8.3.
- PostgreSQL persistente.
- Mailpit para correo QA.
- Queue Worker.
- Scheduler.
- Vite.
- Datos demostrativos ABCD.
- Pruebas PHPUnit y verificación web.
- Respaldos SQL.

## Inicio automático

Al crear o reconstruir el Codespace, GitHub ejecuta:

```text
.devcontainer/install-siget.sh
```

Los puertos se publican automáticamente:

```text
8000  SIGET
8025  Mailpit
```

## Credenciales

```text
Usuario: admin@siget.local
Contraseña: SigetQA_2026_Cambiar!
```

## Comandos principales

```bash
bash INICIAR_SIGET.sh
bash PROBAR_ABCD_QA.sh
bash VERIFICAR_SIGET.sh
bash DIAGNOSTICAR_SIGET.sh
bash RESPALDAR_BASE_QA.sh
bash DETENER_SIGET.sh
```

## Datos persistentes

La base PostgreSQL y Mailpit se almacenan en `.codespace`. Se conservan al detener y volver a iniciar el mismo Codespace.

## Importación en un repositorio vacío

Suba el ZIP al Codespace y ejecute:

```bash
unzip -q SIGET_ABCD_CODESPACES_QA_AUTOMATICO_V2_0.zip
bash SIGET_ABCD_CODESPACES_QA_AUTOMATICO_V2_0/IMPORTAR_EN_REPOSITORIO_CODESPACES.sh
```

Después seleccione:

```text
Codespaces: Rebuild Container
```
