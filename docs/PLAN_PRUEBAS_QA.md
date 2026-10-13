# SIGET — Plan vigente de pruebas QA

## Automatización

En Codespaces, ejecutar `make test` para suite PHP y verificación de servicios. `make validate` agrega migraciones, matriz de permisos QA, build Vite y compilación de cachés. Fuera de Codespaces, ejecutar `php artisan test` y `npm run build` sobre una base de pruebas.

GitHub Actions ejecuta la suite general y la aceptación del flujo controlado con PostgreSQL. Revisar que todos los trabajos finalicen correctamente para el commit seleccionado.

## Aceptación manual

| Etapa | Comprobación |
|---|---|
| Instalación | Crear/reconstruir Codespace, instalación completa, aplicación 8000 y Mailpit 8025 disponibles |
| Reanudación | Volver a abrir el mismo Codespace; servicios recuperados, sin regenerar ni perder datos |
| Administración | Crear usuario con rol/dirección coherentes; permisos, dependencias, plantillas, auditoría y operación |
| Pauta | Importar muestra `public/samples/Pauta_Bienestar_QA.xlsx` con año 2026, comprobar marcas X, vista previa, confirmación única y agrupación por fecha |
| Calendario | Ventanas efectivas, suspensiones y reprogramación; habilitación por scheduler |
| Aislamiento | Operativo y Director de cada Dirección no pueden abrir, descargar ni revisar datos de la otra; probar también URLs directas e historial |
| Evidencia | Archivos permitidos, mínimos/máximos por versión, envío, bloqueo y responsable |
| Corrección | Motivo obligatorio, nueva versión, ventana autorizada cuando vence el plazo, versiones antiguas conservadas |
| Revisión | Primero Director de la Dirección, luego enlace; no aprobar entrega propia |
| Fiscalización | Solo cargas asignadas; dictamen especializado separado del estado institucional |
| Cierre | Ambas Direcciones completas, periodo concluido, checklist, paquete, firmado, manifiesto de integridad, constancia y bloqueo |
| Reapertura | Permiso, motivo y entregables concretos; historial conservado; nueva revisión y firma antes de cerrar otra vez |
| Correo | Alertas al alcance correcto, cierre y aviso informativo a Contabilidad en Mailpit |
| Reportes | Exportaciones CSV/XLSX/PDF, filtros y conteos consistentes con el tablero |
| Cuentas | Desactivar usuario con sesión abierta y comprobar revocación en la siguiente petición |

## Resultado

Registrar commit, entorno y resultado de cada comprobación. La suite automática no sustituye probar la reconstrucción y navegación del Codespace real ni la certificación de carga, antivirus, almacenamiento y restauración institucional.
