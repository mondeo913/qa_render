# Matriz de cumplimiento ABCD

## Fase A — Ingeniería y diseño

| Componente acordado | Implementación QA |
|---|---|
| Arquitectura modular monolítica | Laravel 12 organizado por controladores, servicios, políticas y modelos |
| PostgreSQL | Servicio PostgreSQL 17 |
| Roles y permisos | Ocho perfiles vigentes y dos códigos heredados; matriz configurable |
| Separación por dirección | `user_scopes`, `AccessScopeService` y filtros de relaciones |
| Auditoría | `audit_logs` y vistas administrativas |
| Suspensión 25/08–08/09/2026 | Regla y reprogramación incluidas |

## Fase B — Desarrollo

| Función | Implementación QA |
|---|---|
| Excel horizontal meses/días/X | `HorizontalPautaParser` |
| Agrupación por fecha | `CalendarImportService` |
| Calendario inteligente | FullCalendar (calendario) |
| Evidencias por dirección | Entregables, carpetas y permisos por unidad |
| Versionado | `current_version` y `revision_number` |
| Revisión | Validación por Dirección, revisión institucional y dictamen fiscalizador separado |
| Cierre | Checklist, paquete, documento firmado y certificado |
| Contabilidad | Aviso informativo, sin módulo financiero |
| Dashboards | Chart.js con tendencias, estados, unidades y embudo |
| Administración | CRUD básico real de los catálogos principales |
| Reportes | CSV y PDF ejecutivo |

## Fase C — Producto y QA

| Elemento | Implementación QA |
|---|---|
| Ambiente reproducible | `.devcontainer` + Docker Compose |
| Usuarios QA | Datos automáticos |
| Correos de prueba | Mailpit |
| Pruebas | PHPUnit y `.devcontainer/run-qa-tests.sh` |
| CI | GitHub Actions |
| Diagnóstico | Script y logs |
| Dataset para gráficas | 96 cargas aproximadas entre dos dependencias |

## Fase D — Operación

| Elemento | Implementación QA |
|---|---|
| Centro de Operaciones | Salud, SLA, incidentes y respaldos |
| Queue Worker | Supervisord |
| Scheduler | Supervisord |
| Alertas | Reglas, notificaciones y vista |
| Métricas | Tablas y servicios operativos |
| Respaldos | Comandos y registro QA; restauración institucional pendiente |
| Runbooks | Scripts de inicio, reinicio, diagnóstico y pruebas |

## Resultado

Esta entrega es una línea base funcional de QA y desarrollo. No se declara producción definitiva hasta ejecutar pruebas de aceptación, seguridad, carga, antivirus, almacenamiento institucional y despliegue controlado.
