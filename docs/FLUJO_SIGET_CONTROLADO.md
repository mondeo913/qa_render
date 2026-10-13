# SIGET: flujo controlado antes de API y despliegue en máquinas virtuales

## Alcance

La aplicación web conserva el calendario, tablero, repositorio, reportes y auditoría existentes. Esta adecuación concentra reglas de negocio en servicios reutilizables por la futura API. No instala servicios en las máquinas virtuales ni declara certificación productiva.

## Responsabilidades

| Perfil | Participación | Límite |
|---|---|---|
| Operativo de Transmisión | Captura, corrige y envía entregables de Transmisión | Dirección y responsable asignados; alcances adicionales necesitan escritura |
| Operativo de Programación y Continuidad | Captura, corrige y envía sus entregables | Mismo aislamiento aplicado a Programación y Continuidad |
| Director de cada Dirección | Aprueba, observa o rechaza la versión enviada de su Dirección | No modifica la otra Dirección ni ejecuta cierre institucional |
| Enlace institucional | Importa y confirma pautas; revisión institucional; checklist, firma, cierre, correcciones y reapertura | Dependencias asignadas |
| Director General | Seguimiento consolidado y reportes | Supervisión sin revisión ni cierre operativo |
| Fiscalizador | Dictamen especializado sobre expedientes asignados | Su dictamen no reemplaza la validación ni modifica el estado institucional |
| Administrador | Usuarios, permisos, operación y soporte institucional | Las mismas condiciones del expediente se exigen para firmar/cerrar |

Los códigos heredados DIRECTOR y OPERADOR se mantienen para compatibilidad; aplican los mismos límites estructurales. No se cambian automáticamente identidades ni direcciones de cuentas existentes.

## Recorrido

1. Enlace o administrador importa el Excel en una dependencia permitida y confirma su vista previa una sola vez.
2. Se crean las cargas por fecha y los entregables de ambas Direcciones, con sus responsables y repositorios.
3. El operativo adjunta archivos dentro de la ventana efectiva, respetando extensión, tamaño y máximo por versión.
4. Enviar congela la versión. No se permite enviar una versión observada sin adjuntar una nueva, ni reenviar una ya enviada.
5. El Director revisa su Dirección: aprobar pasa a EN_REVISION; observar/rechazar requiere motivo y devuelve el trabajo al operativo.
6. El enlace o administrador hace la revisión institucional de esa versión después de la aprobación de Dirección. Aprobar pasa a VALIDADO.
7. Todos los entregables obligatorios de todas las Direcciones deben cumplir. El checklist y la firma se habilitan después del periodo original, conservando la regla preexistente.
8. Se genera el expediente para firma con archivos de las versiones vigentes. Se adjunta el documento firmado.
9. El cierre comprueba la existencia e integridad SHA-256 de evidencias y firmado, guarda manifiesto y constancia, bloquea el expediente y genera avisos después de confirmar la transacción.

## Correcciones y reapertura

- Una observación dentro de una ventana habilitada permite cargar una versión nueva; los archivos anteriores y sus revisiones se conservan.
- Si el plazo venció o se requiere cambiar una evidencia validada, el enlace/administrador autoriza entregables específicos, apertura, cierre y motivo. La autorización no abre otros entregables ni supera una suspensión vigente.
- La ventana se consume al enviar y queda vinculada a la versión enviada. La entrega tardía conserva su fecha original; el cierre reconoce la excepción autorizada, no borra el incumplimiento.
- Reabrir un expediente cerrado exige permiso específico y motivo. Se conserva el cierre anterior en una instantánea de auditoría, incluyendo manifiesto, documento firmado y ruta de su constancia. Las constancias se guardan por hash y no se sobrescriben.
- Solo se devuelven los entregables seleccionados; los demás mantienen su validación. El checklist y las firmas previas dejan de estar vigentes. Se requiere nueva validación y nuevo firmado antes del siguiente cierre.
- Reprogramar cambia una carga programada/abierta/vencida/suspendida; no sustituye corrección ni reapertura de expedientes en revisión o cerrados. El scheduler abre la nueva ventana una sola vez y respeta suspensiones.

## Acceso y conservación

- Estado ACTIVE y rol activo se comprueban en cada petición autenticada. Desactivar revoca sesiones de base de datos y el token de recordar; otros controladores de sesión quedan bloqueados en la siguiente petición.
- La autorización aplica también a rutas directas de evidencias, descarga, revisión, historial, plantillas y confirmación de pautas; esconder un botón no concede ni reemplaza permisos.
- Crear/editar usuarios valida correspondencia entre perfil, Dirección y dependencia.
- Desactivar una dependencia conserva cargas, archivos, usuarios relacionados e historial; no ejecuta eliminación en cascada.
- Las credenciales QA no se muestran por la ruta administrativa en producción.

## Actualización de una instalación existente

1. Respaldar base de datos, archivos y configuración institucional, fuera de la actualización. Probar primero en una copia de QA con PostgreSQL.
2. Aplicar el código y ejecutar `php artisan migrate --force`. La migración agrega versión de revisión, ventanas de corrección y restringe capacidades de cierre de directores, sin volver a sincronizar toda la matriz personalizada.
3. Ejecutar `php artisan optimize:clear` y reiniciar workers con `php artisan queue:restart`. Mantener el scheduler activo para habilitar reprogramaciones.
4. No ejecutar `migrate:fresh`, seeders de demostración ni reinicializadores QA sobre datos reales.
5. Verificar que cada Director/operativo tenga la Dirección correcta y que cada enlace tenga dependencia o alcances explícitos. Cuentas mal asignadas no amplían acceso por tener permisos.
6. La sesión PostgreSQL usa `APP_TIMEZONE`, igual que la aplicación (por defecto `America/Mexico_City`). Verificar fechas ya almacenadas en QA si se escribieron antes con una zona de sesión diferente; no se desplazan masivamente los datos existentes.
7. Revisiones antiguas sin número de versión permanecen como históricas. No se presume que certifican una versión vigente. Los expedientes abiertos que dependan de ellas requieren corrección/revalidación; los cerrados se conservan bloqueados.

## Pruebas de aceptación automatizadas

`tests/Feature/WorkflowHardeningTest.php` cubre rutas directas entre Direcciones, responsables, cuentas/roles inactivos, archivos bloqueados, orden de aprobaciones, motivo de observación, mínimos y máximos por versión, dictamen fiscalizador independiente, destinatarios de alertas, ventanas tardías, reapertura y nueva firma, integridad física, historial, alcances de escritura, suspensiones, plantillas, importación Excel hasta cierre y reprogramación.

Validación local del 13 de octubre de 2026: PHP 8.3.6, SQLite de pruebas, **80 pruebas y 411 comprobaciones correctas**. Incluye 29 pruebas específicas del flujo controlado. La compilación Vite terminó correctamente. CI incorpora además la aceptación del flujo con PostgreSQL; sus resultados deben revisarse en GitHub Actions antes de desplegar.

Comandos:

```sh
php artisan test
npm ci
npm run build
```

## Siguiente etapa: API y dos máquinas virtuales

- VM 1: aplicación web/API, PostgreSQL, workers, scheduler y auditoría.
- VM 2: archivos institucionales privados; las descargas siguen pasando por autorización de SIGET.
- La API debe llamar a estos mismos servicios y políticas, conservar bloqueos/transacciones y devolver validaciones 422 y prohibiciones 403.
- Wizard de servidor: requisitos, dirección HTTPS, base de datos, almacenamiento VM 2, administrador inicial, correo institucional, workers/scheduler, respaldos y pruebas finales. El usuario final solo necesita navegador y cuenta autorizada.
- Antes de habilitar usuarios reales: verificar almacenamiento institucional, SMTP y colas, antivirus real, transferencia de videos grandes, concurrencia en PostgreSQL y restauración de respaldos. La bandera de antivirus no instala un escáner; con ella activa las evidencias pendientes no avanzan.
- Mantener los archivos sensibles fuera del repositorio GitHub. Este repositorio contiene código y pruebas sintéticas.
- La capacidad 500 GB + 500 GB y la política de respaldo deben medirse en las VM. La ventana de pérdida de hasta 14 días y la dependencia del mismo host físico descritas en la propuesta siguen siendo decisiones de infraestructura.
