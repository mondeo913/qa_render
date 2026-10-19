# Evaluación de usuarios, módulos y flujo de SIGET

Fecha de revisión: **19 de octubre de 2026**. Repositorio: `mondeo913/qa_render`.

## Resultado y alcance

Se revisaron los ocho perfiles vigentes, rutas web y API, políticas de acceso, servicios de negocio, estados, archivos, consultas, reportes y pruebas de regresión. La evaluación usa cuentas y archivos sintéticos aislados; no representa una revisión nominal de las personas o datos de las VM institucionales, a las que todavía debe conectarse infraestructura.

El flujo implementado separa captura, aprobación de Dirección, validación institucional y fiscalización. Consultar un tablero o un calendario no concede facultades de escritura. Las consultas y descargas de evidencia aplican dependencia, Dirección, responsable y asignación fiscalizadora también cuando se intenta abrir una URL directamente.

**Pendiente importante OF-01: cuando el enlace institucional valida y cierra el expediente, SIGET debe generar el oficio institucional. La plantilla definitiva está en elaboración por el usuario; su integración y pruebas siguen pendientes.** La constancia técnica de cierre que existe actualmente no sustituye ese oficio. Este punto impide declarar completo al 100 % el cierre documental institucional, aunque las pruebas técnicas del flujo resulten correctas.

## Matriz por módulo

Abreviaturas: A = administrador; DG = Director General; DT = Director de Transmisión; DPC = Director de Programación y Continuidad; EI = enlace institucional; OT = operativo de Transmisión; OPC = operativo de Programación y Continuidad; F = fiscalizador. “Consulta” siempre respeta el alcance del perfil. “—” indica acción no disponible con los permisos estándar; no es un módulo faltante.

| Módulo o acción | A | DG | DT | DPC | EI | OT | OPC | F |
|---|---|---|---|---|---|---|---|---|
| Dashboard | Global | Ejecutivo global | Su Dirección | Su Dirección | Sus dependencias | Su trabajo | Su trabajo | Asignaciones activas |
| Usuarios: alta, edición, estado | Administra | — | — | — | — | — | — | — |
| Roles y permisos web | Administra | — | — | — | — | — | — | — |
| Dependencias y unidades | Administra | — | — | — | — | — | — | — |
| Catálogos y configuración | Administra | — | — | — | — | — | — | — |
| Plantillas de requisitos de evidencia | Administra | — | — | — | Sus dependencias | — | — | — |
| Calendario y colores de estado | Consulta | Consulta | Su Dirección | Su Dirección | Sus dependencias | Su trabajo | Su trabajo | Sus asignaciones |
| Importar y confirmar pauta | Ejecuta | — | — | — | Sus dependencias | — | — | — |
| Reprogramar carga | Ejecuta con reglas | — | — | — | Ejecuta con reglas | — | — | — |
| Tablero de cargas y filtros | Consulta | Consulta | Su Dirección | Su Dirección | Sus dependencias | Su trabajo | Su trabajo | Sus asignaciones |
| Mis cargas, adjuntar, corregir y enviar | Soporte autorizado | — | — | — | — | Su Dirección y responsable | Su Dirección y responsable | — |
| Revisión de Dirección | — | — | Su Dirección | Su Dirección | — | — | — | — |
| Revisión institucional | Ejecuta con reglas | — | — | — | Sus dependencias | — | — | — |
| Asignar fiscalizador | Ejecuta | — | — | — | Sus dependencias | — | — | — |
| Dictamen fiscalizador | — | — | — | — | — | — | — | Asignación activa |
| Checklist, expediente para firma y firmado | Ejecuta con reglas | — | — | — | Sus dependencias | — | — | — |
| Validar y cerrar expediente | Ejecuta con reglas | — | — | — | Sus dependencias | — | — | — |
| Autorizar corrección o reapertura | Ejecuta con motivo | — | — | — | Sus dependencias y motivo | — | — | — |
| Repositorio y descarga de evidencias | Global | Global | Su Dirección | Su Dirección | Sus dependencias | Su trabajo | Su trabajo | Sus asignaciones |
| Reportes e Inteligencia | Consulta | Consulta | Su Dirección | Su Dirección | Sus dependencias | Su trabajo | Su trabajo | Sus asignaciones |
| Exportar PDF, CSV y XLSX de reportes | Sí | Sí | — | — | Sí | — | — | Sí |
| Historial de estados y revisiones | Global | Global | Su Dirección | Su Dirección | Sus dependencias | Su trabajo | Su trabajo | Sus asignaciones |
| Bandeja de alertas | Sí | — | Sí | Sí | Sí | Sí | Sí | — |
| Centro de Operaciones y respaldos | Consulta técnica | — | — | — | Consulta técnica | — | — | — |
| Logs administrativos | Sí | — | — | — | — | — | — | — |
| Oficio institucional de cierre OF-01 | Pendiente de plantilla | Consulta a definir | Consulta a definir | Consulta a definir | Generación pendiente al cerrar | Consulta a definir | Consulta a definir | Consulta a definir |

El administrador tiene capacidades de soporte amplias, pero no puede saltar la aprobación previa de Dirección, validar su propia entrega, omitir archivos, ignorar una suspensión o cerrar un expediente incompleto. Su revisión de evidencia es institucional; no reemplaza la etapa del Director.

DG y F no tienen bandeja de alertas en la matriz estándar. Las notificaciones por correo que les correspondan se determinan por el acceso al expediente. El Centro de Operaciones muestra salud técnica global, no un tablero de una dependencia; el enlace sólo consulta y no puede ejecutar respaldos desde el navegador.

## Evaluación usuario por usuario

### 1. Administrador

**Entrada:** crea cuentas, roles, dependencias y unidades; configura requisitos y operación. **Salida:** cuentas activas y alcances explícitos que llegan a los demás módulos.

- Crear una dependencia prepara sus dos Direcciones y plantilla; una cuenta general recibe los alcances de las dependencias nuevas sin duplicar su identidad.
- Editar nombre o correo conserva el identificador y las relaciones. Un correo incompleto como `enlace_institucional@` es inválido; un correo completo y único se guarda. El formulario conserva los errores y valores para corregirlos.
- Rol, Dirección y dependencia deben ser coherentes. Desactivar usuario o rol impide continuar con sesión o token. Cambios sensibles revocan tokens.
- Archivar una dependencia conserva expedientes, archivos e historial; no se borra información por cascada.
- Acceso técnico a operación, respaldos y logs. La ejecución mensual y restauración pertenecen al kit de infraestructura con credenciales separadas.

**Resultado:** separación administrativa y propagación de catálogos cubiertas por pruebas. Antes de producción debe completar el alta de personas reales y comprobar sus asignaciones; las cuentas QA no se instalan en producción.

### 2. Director General

**Entrada:** cargas y entregables de ambas Direcciones. **Salida:** seguimiento ejecutivo, gráficas y reportes consolidados.

- Consulta calendario, tablero, repositorio, Inteligencia e historial sin modificar los estados.
- Las gráficas de composición y barras se calculan sobre entregables visibles; borradores no se cuentan como entregas enviadas y vencimientos no se presentan como validaciones.
- Exporta reportes con los mismos filtros de pantalla.
- No carga, aprueba, firma, cierra, reabre ni administra usuarios con el perfil estándar.

**Resultado:** supervisión preservada. Los permisos de consulta no permiten ejecutar acciones institucionales por URL o API.

### 3. Director de Transmisión

**Entrada:** versiones enviadas por operativos de Transmisión en dependencias con esa Dirección. **Salida:** aprobación, observación o rechazo de Dirección para la versión vigente.

- Consulta dashboard, calendario, tablero, repositorio, reportes, alertas e historial de Transmisión.
- Consolida dependencias de su Dirección; no depende de crear un Director diferente por institución.
- No abre ni descarga archivos de Programación y Continuidad por conocer sus identificadores.
- No revisa borradores, no revisa su propia entrega y debe explicar una observación o rechazo.
- Aprobar deja la evidencia en `EN_REVISION` para el enlace; no cierra el expediente. No dispone de exportación de reportes en los permisos estándar actuales.

**Resultado:** la aprobación de Transmisión llega a revisión institucional sin conceder acceso a la otra Dirección.

### 4. Director de Programación y Continuidad

**Entrada:** versiones enviadas por operativos de Programación y Continuidad. **Salida:** revisión de su Dirección para la misma versión del archivo.

- Aplica las mismas validaciones que Transmisión, con aislamiento estructural `DIR_B`.
- La carga puede ser compartida entre Direcciones, pero los entregables, archivos, embudos, responsables e historial visibles siguen limitados a su Dirección.
- No cierra, reabre ni autoriza correcciones institucionales. No valida Transmisión y no exporta reportes con la matriz estándar actual.

**Resultado:** simetría con Transmisión cubierta en consultas, permisos, carga compartida y calendario.

### 5. Enlace institucional

**Entrada:** pauta de una dependencia autorizada, evidencia aprobada por cada Dirección y documento firmado. **Salida:** expediente completo validado, cerrado, bloqueado y trazable.

- Selecciona la dependencia antes de importar. Se aceptan pauta horizontal con X y fechas `AA.MM.DD` contenidas en “Nombre del Archivo para bitácora”. Se usa la fecha programada del nombre, no la hora del archivo ni la fecha de carga del Excel.
- Confirma la vista previa una sola vez. Las fechas generan cargas de la dependencia seleccionada y entregables de ambas Direcciones, visibles a sus usuarios autorizados.
- Revisa después de la aprobación de Dirección de la versión actual. No puede validar otra dependencia fuera de su alcance.
- Checklist, expediente para firma y firmado requieren cumplimiento completo de ambas Direcciones y las condiciones temporales del expediente.
- Cerrar comprueba existencia física, antivirus e integridad, guarda manifiesto y constancia técnica y bloquea modificaciones ordinarias. Los avisos se producen después de confirmar la transacción.
- Corrección tardía y reapertura exigen motivo, entregables concretos y ventana; conservan antecedentes y requieren nueva validación y firma.

**Resultado:** flujo técnico cubierto. **Falta OF-01: generar el oficio institucional al validar y cerrar, usando la plantilla definitiva que el usuario está trabajando.** No se ha sustituido esa plantilla con un formato inventado. Véase también el hallazgo DOC-02 sobre consulta de documentos finales.

### 6. Operativo de Transmisión

**Entrada:** fechas de pauta de sus dependencias autorizadas y entregables de Transmisión asignados a él o disponibles sin responsable. **Salida:** archivos con versión enviados a su Director.

- Su dashboard presenta pendientes, vencimientos, revisión, observaciones y validados del trabajo que le corresponde.
- Calendario, filtros de pautas y carpetas de dependencias usan el mismo alcance, incluso si todavía no existen archivos en una carpeta.
- Adjunta en la ventana permitida y con requisitos de extensión, tamaño, mínimo/máximo y antivirus. La primera carga reclama el entregable disponible de forma transaccional.
- Enviar congela la versión; una observación se corrige con versión nueva. El expediente cerrado permanece bloqueado hasta una autorización institucional.
- No consulta al responsable de otro entregable, no cruza a Programación y Continuidad y no aprueba su propio trabajo.

**Resultado:** captura, seguimiento y aislamiento cubiertos; no obtiene facultades de revisión ni cierre.

### 7. Operativo de Programación y Continuidad

**Entrada y salida:** equivalentes a Transmisión, aplicadas a `DIR_B` y a sus entregables.

- Los filtros incluyen las pautas del alcance completo y se actualizan al cambiar de dependencia. Una pauta no se mezcla con otra por compartir plantilla.
- El repositorio muestra dependencias autorizadas aunque sólo algunas tengan pautas; no requiere crear archivos ficticios para mostrar sus carpetas.
- Los colores y etiquetas del calendario se conservan también en modo lectura y al cambiar de tema.
- Un alcance adicional de sólo lectura no habilita la carga de archivos en otra dependencia.

**Resultado:** cubierto de forma simétrica, con regresiones específicas del problema de pautas faltantes en filtros y del repositorio que mostraba sólo tres dependencias.

### 8. Fiscalizador

**Entrada:** asignación activa a un expediente, realizada por enlace o administrador. **Salida:** dictamen independiente por versión.

- Consulta sólo expedientes asignados y sus evidencias; el rol por sí mismo no concede acceso global.
- Su dashboard distingue pendientes de fiscalizar, aprobados y observados de su propia actuación sobre la versión vigente.
- Puede dictaminar evidencia enviada y auditar un expediente cerrado. Su decisión no cambia validación, cierre ni semáforo institucional.
- No sube evidencia operativa, administra usuarios, importa pauta, firma, cierra ni reabre.
- Al retirar la asignación desaparece inmediatamente el expediente de detalle, listado, dependencias y calendario, incluso con un token existente.

**Resultado:** independencia y revocación cubiertas. La asignación activa debe mantenerse actualizada por los responsables institucionales.

## Validación del recorrido completo

| Paso | Actor | Condición de entrada | Resultado que debe conservarse |
|---|---|---|---|
| 1. Pauta | EI / A | Dependencia autorizada; archivo válido | Vista previa con fechas y errores; aún sin duplicar cargas |
| 2. Confirmación | EI / A | Vista previa válida y no confirmada | Cargas por fecha, dependencia y ambas Direcciones |
| 3. Captura | OT / OPC | Responsable, Dirección y ventana autorizados | Archivo privado con hash y versión; estado de captura |
| 4. Envío | OT / OPC | Archivos vigentes completos y análisis satisfactorio | Evidencia `ENVIADO`; edición ordinaria bloqueada |
| 5. Revisión de Dirección | DT / DPC | Versión enviada de su Dirección | Aprobación a `EN_REVISION`; observación/rechazo con motivo |
| 6. Revisión institucional | EI / A | Aprobación de Dirección de la versión vigente | Evidencia `VALIDADO`; agregación del expediente completo |
| 7. Firma | EI / A | Ambas Direcciones cumplen, periodo y checklist válidos | Paquete PDF y firmado incorporado con integridad |
| 8. Cierre | EI / A | Archivos, firmado, antivirus y verificaciones completos | `VALIDADO_Y_CERRADO`, manifiesto, constancia y bloqueo |
| 9. Oficio institucional | EI al cerrar | **Plantilla definitiva pendiente** | **OF-01 pendiente de implementar y probar** |
| 10. Consulta / fiscalización | Usuarios dentro de su alcance / F asignado | Permiso vigente | Consulta sin mutar el expediente; dictamen fiscal independiente |
| 11. Corrección o reapertura | EI / A | Motivo, entregables y ventana explícitos | Historial conservado; validación y firma nuevas cuando correspondan |

Una aprobación de una versión anterior no valida una versión nueva. El cierre exige ambas Direcciones, no sólo la parte visible del Director u operativo que consulta. La reapertura no borra la constancia ni el manifiesto anteriores.

## Hallazgos y necesidades pendientes

| ID | Prioridad y estado | Hallazgo / efecto | Resolución o criterio de salida |
|---|---|---|---|
| OF-01 | **Alta · pendiente importante** | Falta el oficio institucional que debe producirse al validar y cerrar; plantilla aún en elaboración | Recibir plantilla definitiva, integrar generación y descarga autorizada y probar cierre, reintento y reapertura |
| DOC-02 | Media · pendiente funcional | La constancia técnica se genera y almacena; no hay ruta dedicada para descargarla desde el expediente. El firmado se conserva, pero la descarga genérica actual sólo admite archivos ligados a evidencia | Completar la consulta autorizada de firmado, constancia y futuro oficio, con historial de documentos y límites entre Direcciones; no usar rutas públicas al almacenamiento |
| CAL-03 | Media · corregido en esta entrega | PostgreSQL podía devolver fechas programadas sin orden estable | Orden cronológico explícito y regresión con PostgreSQL |
| QA-04 | Alta · corregido en esta entrega | Una tubería hacia el registro de CI podía ocultar el fallo del arranque del antivirus | Propagar el fallo con `pipefail`, exigir marcador final de aceptación y conservar diagnóstico de salud; no aceptar un trabajo sólo por su etiqueta verde |
| BK-05 | Alta · corregido en esta entrega | Verificar hashes no demuestra que una copia se pueda restaurar | Distinguir `HASH_VERIFIED`, `VERIFIED` y `RESTORE_FAILED`; marcar verificado sólo tras restaurar base y archivos y contrastar conteos y hashes |
| OPS-06 | Media · corregido en esta entrega | Un respaldo interrumpido podía dejar mantenimiento activo; Nginx podía conservar la dirección anterior de PHP | Recuperación por watchdog, espera de PHP y resolución dinámica de su dirección; se conserva mantenimiento manual. Reinicio en VM real pendiente de aceptación |
| INF-07 | Alta · aceptación de infraestructura pendiente | Aún no se han aceptado red, NFS, DNS/TLS, SMTP, capacidad y reinicio en las VM asignadas | Ejecutar la matriz de la guía de infraestructura y registrar resultados reales |
| ACC-08 | Media · configuración de instalación pendiente | Las personas reales y sus asignaciones no están disponibles en este entorno de evaluación | Administrador verifica cuentas activas, Dirección, dependencias, responsables y asignaciones fiscales en producción |

La API v1 comparte servicios y políticas con la web. Sus 37 operaciones cubren autenticación, consultas, pauta, evidencia, revisión, cierre y administración esencial. No se presume que todos los formularios web tengan un endpoint público equivalente: catálogos adicionales, gestión completa de plantillas, bandeja de notificaciones e historial mantienen su interfaz web en esta versión. El contrato exacto está en `docs/api/openapi.json`.

## OF-01: integración del oficio cuando esté lista la plantilla

**Responsable de contenido y diseño:** usuario / área institucional. **Responsable de integración:** desarrollo SIGET. **Disparador solicitado:** enlace institucional valida y cierra el expediente.

1. Recibir la versión aprobada de la plantilla y confirmar sus campos, textos, firmantes, folio y formato de salida. No se presupone un esquema de numeración ni una firma electrónica avanzada.
2. Mapear cada campo contra la dependencia, pauta, fechas programadas, expediente, cierre y datos institucionales reales; marcar expresamente los campos que deban capturarse.
3. Generar el oficio vinculado al cierre confirmado. Definir el comportamiento si la generación falla para evitar informar que existe un oficio que no se produjo.
4. Conservar la versión de plantilla, documento producido, fecha, usuario e integridad; no duplicarlo ante un reintento del mismo cierre.
5. Ofrecer consulta y descarga según permisos acordados, por web y API, sin exponer el directorio de VM2.
6. En una reapertura, conservar el oficio anterior y definir su vigencia y la generación de uno nuevo para el siguiente cierre.
7. Probar datos completos/incompletos, permisos, reintento, cierre concurrente, reapertura, formato y legibilidad contra la plantilla final aprobada.

**Criterio de aceptación:** el enlace completa un cierre válido y obtiene el oficio correcto conforme a la plantilla institucional; una persona sin alcance no puede obtenerlo y un reintento no duplica folios ni documentos. OF-01 seguirá abierto hasta cumplirlo. Se programó un recordatorio para el 20 de octubre de 2026 por la mañana, hora de Ciudad de México.

## Evidencia y límites de la validación

La regresión local completa de esta evaluación terminó con **180 pruebas y 3309 comprobaciones correctas**, sobre PHP 8.3 y SQLite de pruebas. Se validaron además sintaxis PHP/Bash/Python y configuración YAML. La ejecución PostgreSQL y la aceptación de contenedores se verifican por separado en CI; no se extrapola el resultado de SQLite a las VM reales.

- `UserModuleFlowTest`: matriz de entrada de 19 módulos para ocho perfiles, detalle de expediente sin mutación, paridad de límites web/API, prohibición de cierre/reapertura y retirada inmediata de asignación fiscal. **9 pruebas, 294 comprobaciones correctas** en la ejecución local de esta evaluación.
- `WorkflowHardeningTest` y `ApiWorkflowTest`: pauta a cierre, dos Direcciones, aprobación por versión, correcciones, suspensión, archivos alterados, inactividad, permisos y revocación.
- `DashboardRoleFlowTest`: contadores, gráficas, responsables, filtros y reportes coherentes; consulta sin cambios al flujo.
- `CalendarAgencyFlowTest`, `CalendarPresentationTest` y `HorizontalPautaParserTest`: nuevas dependencias, fechas X / nombre de archivo, ambas Direcciones y presentación de estados.
- `LoadBoardFilterFlowTest`, `RepositoryAgencyFlowTest` y `AgencyOnboardingTest`: catálogo completo de pautas/dependencias, carpetas vacías autorizadas, no duplicación y separación entre responsables.
- `AdminUserEditTest`, `AccessScopeIsolationTest` y `SpecificRolePermissionsTest`: edición de usuario, coherencia estructural y acceso directo.
- `InfrastructureDeliveryTest`: instalación sin QA, repositorio privado obligatorio, antivirus, integridad, restauración y vigilancia mensual.
- `deploy/tests/watchdog-recovery.sh`: bloqueo durante respaldo, mantenimiento manual, respaldo aún activo tras perder su lanzador, recuperación de interrupción, reintento fallido y reinicio de servicios no saludables.
- `AccountingNoticeTest`: aviso informativo e idempotente; reapertura y nuevo cierre preservan comportamiento.
- `tests/browser/ui.mjs`: navegación real de los ocho perfiles, gráficas, controles Bootstrap, colores de calendario y reportes a 1440, 768 y 390 píxeles.

La aceptación de contenedores ejecuta HTTPS, PostgreSQL, rechazo EICAR real, flujo API de ocho perfiles, fallo del repositorio, reinicio de worker y restauración completa. El resultado válido es el trabajo **Release acceptance gate** del mismo commit, que depende de todos los trabajos anteriores. Un paquete construido no acredita por sí solo que toda la versión pasó. CI usa repositorio de archivos aislado; NFS y SMTP institucionales se aceptan en las VM reales.

Los respaldos son **mensuales**, además del inicial y ejecuciones manuales autorizadas. La retención, espacio disponible y tiempo de recuperación deben aceptarse con infraestructura; no se promete disponibilidad absoluta ante una VM, red o almacenamiento apagados. Consulte `docs/ENTREGA_INFRAESTRUCTURA_API.md`.

Para repetir las comprobaciones de aplicación en una copia de pruebas:

```bash
php artisan test --filter=UserModuleFlowTest
php artisan test
npm ci
npm run build
```

Las pruebas de producción aisladas corresponden exclusivamente a CI. No ejecutar fixtures, reinicializaciones QA ni `migrate:fresh` sobre una instalación institucional.
