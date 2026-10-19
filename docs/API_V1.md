# API SIGET v1

Base: `https://URL_INSTITUCIONAL/api/v1`. Contrato importable en Swagger o Postman: [openapi.json](api/openapi.json). La web existente y la API utilizan los mismos servicios, políticas y datos; no se duplican expedientes ni se modifica el orden de aprobación.

La URL del portal puede ser accesible en la red autorizada. Los datos y archivos requieren una cuenta activa y permisos; no existe registro público. El asistente se ejecuta en VM1 por infraestructura, no en las computadoras de los usuarios.

Para integrar sistemas externos de forma segura, consulte [Integración de clientes API (Node.js y pruebas QA)](INTEGRACION_CLIENTES_API.md). El acceso web Laravel conserva su sesión y CSRF; no utiliza tokens Bearer en el navegador.

## Autenticación

`POST /auth/token` recibe JSON con `email`, `password` y `device_name`. Una cuenta válida obtiene `201` con `token_type: Bearer`, `access_token`, `expires_at` y sus permisos. El token completo se entrega una vez; en la base se conserva su hash. Su vigencia predeterminada es 480 minutos, limitada a 24 horas por token. Nunca guarde tokens en URLs, repositorios ni logs.

Las peticiones siguientes envían `Authorization: Bearer TOKEN`. `GET /me` devuelve la identidad y permisos actuales. `DELETE /auth/token` revoca la sesión API actual; `DELETE /auth/tokens` revoca todas las del usuario. La web mantiene su sesión del navegador y protección CSRF habitual. La API usa tokens y no acepta una sesión web como sustituto.

La desactivación de usuario o rol impide el siguiente acceso. Los cambios de contraseña, rol, dependencia o dirección revocan tokens; los cambios del perfil general también revocan accesos cuando alteran alcances. Los permisos y asignaciones se comprueban en cada solicitud.

## Operaciones

| Operación | Finalidad |
|---|---|
| `GET /agencies` | Dependencias visibles del usuario |
| `GET /dashboard` | Indicadores, distribuciones y series para gráficas dentro del alcance |
| `GET /loads`, `GET /loads/{load}` | Cargas paginadas y entregables visibles |
| `GET /calendar/events` | Fechas programadas, dependencia, estado y color; también para sólo lectura |
| `POST /imports`, `GET /imports/{import}`, `POST /imports/{import}/confirm` | Validación, vista previa y confirmación única de pauta |
| `POST /evidences`, `GET /evidences/{evidence}` | Carga y consulta de evidencia, archivos y versiones |
| `POST /evidences/{evidence}/submit` | Enviar y bloquear la versión |
| `POST /evidences/{evidence}/reviews` | Revisión de dirección, institucional o fiscalización |
| `GET /files/{file}/download` | Descargar un archivo autorizado sin revelar rutas físicas |
| `POST /loads/{load}/checklist` | Verificación institucional de ambas direcciones |
| `POST /loads/{load}/signature-package` | PDF del expediente para firma |
| `POST /loads/{load}/signed-document` | Incorporar el documento firmado |
| `POST /loads/{load}/close` | Cierre con integridad y bloqueo |
| `POST /loads/{load}/corrections`, `POST /loads/{load}/reopen` | Corrección o reapertura autorizada con historial |
| `POST /loads/{load}/reschedule` | Reprogramar una carga que todavía lo permita |
| `PUT /loads/{load}/fiscal-assignment` | Asignar un fiscalizador activo |
| `GET /reports/pdf`, `/reports/csv`, `/reports/xlsx` | Reportes con permiso de exportación y los mismos filtros de la web |
| `GET /admin/roles`, `GET/POST /admin/users`, `PUT /admin/users/{user}` | Administración de cuentas y perfiles |
| `PATCH /admin/users/{user}/status` | Activación o desactivación idempotente |
| `GET/POST /admin/agencies`, `PUT/DELETE /admin/agencies/{agency}` | Catálogo institucional; DELETE archiva y conserva historial |

Las operaciones administrativas exigen sus permisos existentes (`users.manage`, `agencies.manage`). `general_profile: true` en un usuario habilita el perfil general; los operativos requieren una dirección raíz compatible. Los fiscalizadores siempre requieren una asignación activa al expediente. Una edición de nombre sin cambios de alcance conserva restricciones explícitas existentes.

## Pautas y flujo

El alta de pauta usa `multipart/form-data`: `contracting_agency_id`, `schedule_year` y `calendar_file` XLSX/XLS de hasta 20 MiB. Admite las marcas X de la pauta horizontal y la columna **Nombre del Archivo para bitácora**. Por ejemplo, `_26.07.07.TESTIGO` programa el 7 de julio de 2026 y una fila `_26.07.15.TESTIGO` programa el 15. Agrupa una carga por fecha de esa dependencia y genera los requisitos de ambas direcciones. Confirmar de nuevo devuelve `422` y no duplica cargas.

Los archivos de evidencia usan `multipart/form-data`: `deliverable_id`, `file` y `title` opcional. Se aplican formatos, tamaño, cantidad de archivos, responsable, ventana habilitada y antivirus. Enviar la evidencia impide modificar sus archivos. La revisión institucional requiere aprobación previa de la dirección en la misma versión. Observar o rechazar exige comentarios; una corrección genera otra versión que debe volver a revisarse. La fiscalización no sustituye esas aprobaciones ni cambia el cierre institucional.

El cierre exige las dos direcciones, verificaciones, documento firmado y manifiesto íntegro. La reapertura requiere motivo, entregables y ventana autorizada; conserva el cierre anterior, invalida el firmado y exige nuevas aprobaciones y firma. La API aplica las mismas reglas de la web.

## Consultas y respuestas

Las consultas de cargas admiten `contracting_agency_id`, `calendar_import_id`, `status`, `start`, `end`, `per_page` y `page`. `per_page` admite 1–100, predeterminado 25. `start` y `end` filtran fechas inclusivas en `/loads`. El calendario requiere `start` incluido y `end` excluido, formato `YYYY-MM-DD`, con un máximo de 93 días por consulta. Las fechas de respuesta incluyen zona horaria ISO 8601. Use el estado y `appearance` proporcionados por el servidor para representar colores consistentemente entre perfiles.

Las respuestas JSON usan `data`; las listas paginadas añaden `meta`. El calendario devuelve el arreglo de eventos compatible con la vista web y los reportes devuelven archivos binarios. El contrato describe las excepciones. No se serializan contraseñas, rutas del repositorio ni objetos completos de base de datos.

| Código | Interpretación |
|---|---|
| `200`, `201`, `204` | Consulta, creación o revocación completada |
| `401` | Token ausente, vencido o revocado; identidad no habilitada |
| `403` | Falta de permiso o de alcance; cambiar un ID no amplía acceso |
| `404` | Recurso o ruta inexistente |
| `422` | Campos inválidos, credenciales incorrectas o transición de flujo rechazada |
| `429` | Límite de solicitudes; atender `Retry-After` |
| `503` | Mantenimiento o repositorio privado fuera de servicio |

Los errores siguen siendo JSON aunque el cliente omita `Accept`. Las respuestas incluyen `Cache-Control: no-store, private`. Los límites predeterminados son 5 intentos de acceso por identidad/IP y 30 por IP cada minuto, y 120 solicitudes por usuario autenticado cada minuto. No reintente ciegamente operaciones de envío, revisión o cierre: consulte primero el recurso. La confirmación de pauta protege contra duplicados; el alta de archivos y las revisiones no implementan una clave genérica de idempotencia.

Los respaldos y restauraciones no se exponen como endpoints. Infraestructura los opera en VM1 con el calendario **mensual**, usando la [guía de entrega](ENTREGA_INFRAESTRUCTURA_API.md).
