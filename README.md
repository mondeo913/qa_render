# SIGET — Web institucional y API v1

Sistema de Gestión de Evidencias de Transmisión: pautas Excel, calendario, entregables por Dirección, evidencias versionadas, validación de Dirección, revisión institucional, firma, cierre y auditoría.

## Documentación vigente

- [Entrega a infraestructura, dos VM y respaldos mensuales](docs/ENTREGA_INFRAESTRUCTURA_API.md).
- [API v1 y contrato de integración](docs/API_V1.md).
- [Flujo, responsabilidades y actualización](docs/FLUJO_SIGET_CONTROLADO.md).
- [Crear, reanudar y verificar Codespaces](docs/codespaces/GUIA_PASO_A_PASO.md).
- [Pruebas automáticas y aceptación manual](docs/PLAN_PRUEBAS_QA.md).
- [Repositorio y tablero](docs/REPOSITORIO_Y_TABLERO.md).
- [Matriz funcional](docs/01_MATRIZ_CUMPLIMIENTO_ABCD.md).
- [Pendientes de certificación productiva](docs/04_LIMITACIONES_Y_SIGUIENTE_ETAPA.md).

## Codespaces

Crear un Codespace desde `main` o ejecutar **Codespaces: Rebuild Container** en uno existente. No importar ZIP, overlays ni paquetes antiguos.

- Creación: `.devcontainer/install-siget.sh`.
- Inicio y reanudación: `.devcontainer/qa-runtime.sh`.
- Recuperación manual: `RECUPERAR_SIGET.sh`, que llama al mismo arranque.
- Los workers, scheduler, PostgreSQL y Mailpit siguen gestionados por Supervisor y los watchdogs vigentes.

## Comandos

| Acción | Comando |
|---|---|
| Iniciar o recuperar | `bash INICIAR_SIGET.sh` |
| Verificar servicios | `make verify` |
| Diagnóstico | `make diagnose` |
| Pruebas y verificación web | `make test` |
| Validación integral QA | `make validate` |
| Mostrar URLs | `bash MOSTRAR_URLS.sh` |
| Respaldar PostgreSQL QA | `bash RESPALDAR_BASE_QA.sh` |
| Detener servicios | `make stop` |

La validación integral aplica migraciones y sincroniza permisos QA. No usarla como procedimiento de actualización productiva; seguir la guía de actualización.

SIGET utiliza el puerto 8000 y Mailpit el 8025. Las credenciales de demostración se consultan en el archivo local `.codespace/ACCESOS.txt` generado por la instalación; no son cuentas productivas.

## Mantenimiento

Esta estructura contiene una sola línea de scripts y guías vigentes. Las copias `.bak`, comandos duplicados y manuales sustituidos se retiraron del árbol actual; su historial sigue disponible en Git. Conservar las migraciones históricas: siguen siendo necesarias para crear y actualizar instalaciones existentes.

## Entrega API v1 y dos máquinas virtuales

La instalación para infraestructura se encuentra en [docs/ENTREGA_INFRAESTRUCTURA_API.md](docs/ENTREGA_INFRAESTRUCTURA_API.md). Incluye asistente, HTTPS, repositorio privado en VM2, watchdog y **respaldos mensuales** con verificación y restauración de prueba. La API y su contrato están en [docs/API_V1.md](docs/API_V1.md) y [docs/api/openapi.json](docs/api/openapi.json).

Las instrucciones de Codespaces de este README corresponden al ambiente de desarrollo y QA; producción se instala con `sudo bash deploy/vm1/install.sh` y no usa datos demostrativos.
