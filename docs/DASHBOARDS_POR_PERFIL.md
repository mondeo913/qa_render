# Dashboards y conciliación de SIGET

Cada perfil utiliza el mismo cálculo autorizado de cargas y entregables. Los paneles cambian la presentación y los accesos a las etapas existentes; no conceden permisos ni ejecutan transiciones al consultar información.

| Perfil | Información principal | Seguimiento |
| --- | --- | --- |
| Administrador | Usuarios, base de datos, cola, trabajos fallidos, incidencias y auditoría | Centro de operaciones y supervisión global |
| Director General | Esperados, enviados, revisión, vencimientos, barras por dependencia y comparación entre direcciones | Expedientes institucionales y reportes autorizados |
| Director de Transmisión | Cumplimiento, estados, tendencia y pendientes por responsable de DIR_A | Revisión de evidencias enviadas a su Dirección |
| Director de Programación y Continuidad | Cumplimiento, estados, tendencia y pendientes por responsable de DIR_B | Revisión de evidencias enviadas a su Dirección |
| Enlace institucional | Revisión, observaciones, importaciones pendientes, firma y cierres | Confirmación de pautas y etapas institucionales autorizadas |
| Operador de Transmisión | Captura, correcciones, revisión y plazos de DIR_A | Entregables propios o sin responsable dentro de su alcance |
| Operador de Programación y Continuidad | Captura, correcciones, revisión y plazos de DIR_B | Entregables propios o sin responsable dentro de su alcance |
| Fiscalizador | Asignaciones activas, dictamen propio de la versión actual y antigüedad pendiente | Fiscalización sin modificar estados institucionales |

## Unidades y estados

- **Carga**: fecha programada de una pauta para una dependencia. Puede incluir ambas direcciones.
- **Entregable**: requisito asignado dentro de una carga. Sus archivos y versiones no aumentan el número de entregables esperados.
- **Enviado**: entregable con fecha de envío registrada. Es un indicador acumulativo; puede estar observado o corregido después.
- **Estado actual**: un único grupo por entregable. La suma de los cuatro grupos siempre debe coincidir con los esperados:

| Grupo | Estados |
| --- | --- |
| Pendiente de envío | PENDIENTE, EN_CAPTURA, CORREGIDO |
| En revisión | ENVIADO, EN_REVISION |
| Con observaciones | OBSERVADO, RECHAZADO |
| Validado / cerrado | VALIDADO, CERRADO |

Los pasteles representan estos grupos excluyentes. Esperados, enviados y validados se comparan en barras separadas, porque son etapas acumulativas. Los colores de estado permanecen fijos al cambiar tema o paleta. Cada gráfica incluye una tabla accesible con sus valores; si faltan los recursos compilados, esa tabla se abre como alternativa.

## Alcance, filtros y plazos

El filtro de dependencia, pauta, dirección, responsable, estado del expediente, estado del entregable y periodo se intersecta con `AccessScopeService`. En cargas compartidas, las métricas, evidencias, direcciones y relaciones de detalle se limitan también por entregable. Un filtro manipulado no amplía los permisos. Los reportes y las exportaciones PDF, CSV y XLSX reutilizan estas consultas y cuentan como recibidos únicamente entregables con envío registrado. Los reportes permiten consultar expresamente el estado CANCELADA; las métricas de cumplimiento excluyen esas cargas. Los responsables se agrupan por ID, aunque coincidan sus nombres.

El periodo corresponde a meses de fecha programada, no a días ni a semanas. Cambiar dependencia limpia pauta y responsable anteriores y recarga las opciones. Las direcciones conservan los IDs de todas las dependencias autorizadas, evitando que una selección anterior deje vacío el panel al cambiar dependencia.

El indicador de cargas vencidas incluye ventanas programadas o en captura cuyo límite ya pasó, aunque el proceso de estados aún no haya marcado VENCIDA. Excluye cargas entregadas, suspendidas o bloqueadas, como la advertencia del calendario. El KPI de entregables fuera de plazo cuenta pendientes u observados sin resolver por su fecha límite, con respaldo en la fecha de la carga. Las ventanas autorizadas de corrección se consultan y aplican en el expediente existente; estos indicadores no amplían ni cancelan esas autorizaciones.

Los gráficos por dependencia y responsable permiten abrir su filtro; el pastel permite filtrar por grupo. Las tablas ofrecen los mismos enlaces para teclado y lectura asistida. La bandeja prioriza hasta 15 entregables y muestra el total del filtro; no presenta esa muestra como el universo de las métricas. La tendencia muestra los últimos seis meses con datos. Las importaciones pendientes del enlace se identifican expresamente como una lista independiente del periodo de cargas.

## Comprobaciones de regresión

`DashboardRoleFlowTest` comprueba conciliación de los nueve estados, aislamiento de ambas direcciones en cargas compartidas, filtros combinados, borradores vencidos, responsables con nombres iguales, fiscalización por versión y permisos de acciones.

`WorkflowHardeningTest` recorre captura, envío, aprobación de Dirección, aprobación institucional, firma, cierre y reapertura, conciliando el dashboard en cada etapa. También verifica que consultar el panel no modifique el cierre, archivos, auditoría ni estados. El resto de sus pruebas conserva bloqueos, revisiones, correcciones, asignaciones y restricciones de acceso.

`tests/browser/ui.mjs` comprueba las ocho cuentas, gráficas realmente dibujadas, cifras del pastel, colores tras cambiar tema y paleta, cambio de dependencia, permisos visibles y diseño en escritorio, tableta y teléfono. La aceptación PostgreSQL ejecuta también los casos de dashboard y flujo.

Estos casos usan datos de prueba. La revisión de cifras de una instancia concreta requiere consultar esa instancia con sus propios datos y filtros.
