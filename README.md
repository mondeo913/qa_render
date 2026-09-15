# SIGET — Ambiente QA consolidado

## Objetivo

Este repositorio contiene la versión QA consolidada de **SIGET — Sistema de Gestión de Evidencias de Transmisión**.

La línea QA debe representar una sola versión funcional vigente, sin conservar paquetes, respaldos, instaladores sustituidos o reportes históricos dentro del código fuente.

## Funcionalidad integrada

- autenticación y recuperación de acceso;
- administración de usuarios, roles y permisos;
- Direcciones, dependencias y unidades organizacionales;
- tablero de cargas y seguimiento operativo;
- calendario y pautas;
- carga y gestión de evidencias;
- repositorios y revisión institucional;
- validación y cierre;
- dashboards por alcance de usuario;
- reportes ejecutivos e indicadores;
- exportaciones y diagnóstico QA.

## Alcance por rol

- **Administrador:** administración global y configuración.
- **Enlace Institucional:** revisión, observación, validación y cierre institucional según su alcance.
- **Director General:** supervisión institucional consolidada.
- **Director de Transmisión:** supervisión de su Dirección y unidades descendientes.
- **Director de Programación y Continuidad:** supervisión de su Dirección y unidades descendientes.
- **Operativos:** captura, corrección, envío y seguimiento de cargas y evidencias.
- **Fiscalizador:** revisión especializada definida por el modelo vigente.

## Ambiente QA

La ruta vigente de Codespaces es:

```text
.devcontainer/devcontainer.json
        ↓
Dockerfile.k2
        ↓
install-siget.sh
        ↓
repair-runtime.sh
        ↓
qa-runtime.sh
```

El ambiente utiliza PHP 8.3, PostgreSQL, Supervisor y Mailpit. Node se proporciona mediante la configuración de Dev Containers.

## Reconstrucción

Abrir el repositorio en GitHub Codespaces y ejecutar **Codespaces: Rebuild Container** cuando sea necesario. El `postCreateCommand` ejecuta `.devcontainer/install-siget.sh`.

## Verificación

```bash
bash .devcontainer/verify-siget.sh
bash K2_CERTIFICAR_COMPLETO.sh
```

## Servicios QA

- SIGET: `8000`
- Mailpit: `8025`

## Diagnóstico

```bash
bash INICIAR_SIGET.sh
bash DIAGNOSTICAR_SIGET.sh
bash MOSTRAR_URLS.sh
bash .devcontainer/verify-siget.sh
```

## Certificación

La instalación no equivale a certificación. Antes de promover el sistema se debe reconstruir QA y comprobar dependencias, PostgreSQL, migraciones, seeders, PHPUnit, Vite, permisos por rol, dashboards, filtros, cargas, evidencias, validación, cierre y reportes.

## Regla de mantenimiento

No reincorporar ZIP de entregas anteriores, archivos `backup`, hashes de paquetes, reportes históricos ni instaladores sustituidos. Toda nueva modificación debe partir de la línea vigente y consolidarse antes de preproducción o producción.
