# Alcance de la entrega y aceptación en infraestructura

La entrega API v1 conserva el flujo web de SIGET e incorpora 37 operaciones autenticadas sobre los mismos servicios, permisos, bloqueos y transacciones. El contrato está en [api/openapi.json](api/openapi.json).

Incluye imágenes de aplicación, web HTTPS y ClamAV, PostgreSQL 16, repositorio privado en VM2, asistente de instalación, arranque automático, watchdog y **respaldos mensuales** completos con hashes y restauración aislada. La instalación nueva crea catálogos y un administrador inicial; los datos QA están deshabilitados en producción.

CI valida regresión PHP, flujo y API con PostgreSQL, ocho perfiles en navegador, interfaces responsivas, recuperación de Codespaces, construcción de imágenes, HTTPS, antivirus EICAR y respaldo con restauración real de PostgreSQL. La versión se acepta únicamente cuando todos los trabajos del mismo commit terminan correctamente.

La [guía de infraestructura](ENTREGA_INFRAESTRUCTURA_API.md) contiene los procedimientos y la matriz de aceptación en las dos VM. Infraestructura completa DNS, certificados, red, NFS real, SMTP institucional y las pruebas de reinicio, capacidad y transferencia con archivos reales. CI utiliza un directorio privado aislado como repositorio; no sustituye esas comprobaciones en los servidores asignados.

La firma contemplada en este flujo es la incorporación del documento firmado y su integridad. La propuesta no incluye validación criptográfica de firma electrónica avanzada ni SSO institucional. La retención de respaldos y su capacidad deben quedar acordadas por infraestructura; el sistema conserva copias anteriores y rechaza generar una nueva cuando no existe espacio suficiente.
