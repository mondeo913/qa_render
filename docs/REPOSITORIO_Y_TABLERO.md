# SIGET — Repositorio y tablero vigentes

## Captura y revisión

`/mis-cargas` utiliza su propio controlador y vista para el trabajo del operativo. `/repositorio` presenta el expediente institucional. Comparten entidades de negocio, pero no el listado de captura.

La agrupación de consulta es Dependencia → Mes programado → Pauta → Direcciones → Evidencias → Archivos. Las cargas por fecha generadas al confirmar la pauta conservan sus entregables, revisión y cierre; el agrupamiento visual no crea expedientes paralelos ni permite cerrar una evidencia aislada como expediente institucional.

La validación considera los requisitos obligatorios, ambas Direcciones, cantidades, formatos, tamaños, versión vigente, aprobaciones y fechas. Las responsabilidades y excepciones de plazo se detallan en [el flujo controlado](FLUJO_SIGET_CONTROLADO.md).

## Carpetas y filtros del repositorio

El catálogo de carpetas usa todas las dependencias activas autorizadas para el usuario, aunque todavía no tengan cargas. Una carpeta vacía indica que no hay cargas programadas en su alcance. Consultar el repositorio no crea pautas, entregables, archivos ni filas de almacenamiento: al confirmar una pauta, `RepositoryService` genera los árboles reales de sus cargas y Direcciones mediante el flujo existente.

La selección de dependencia filtra tarjetas, fechas programadas, evidencias, archivos recientes y espacio utilizado. El lateral conserva el catálogo autorizado para poder cambiar de dependencia. La búsqueda reconoce el nombre o código de dependencia, el archivo de pauta, la carga, la plantilla y el mes `AAAA-MM`. Los filtros de pauta, estado y fechas también se aplican al contenido y a los archivos recientes.

Las carpetas de mes/pauta se agrupan por importación de calendario. Dos archivos diferentes que utilicen la misma plantilla se conservan separados. Cada fecha programada ofrece su propio acceso a la carga; agrupar el mes no convierte la primera fecha en el expediente de todas las demás. Los enlaces de reporte incluyen la importación y el periodo mensual y se muestran únicamente a quien tiene permiso de exportación.

Los operativos y directores conservan el aislamiento por Dirección y responsable. Fiscalización solo muestra dependencias con asignaciones activas. Una carpeta visible no amplía los permisos para consultar, descargar, capturar, revisar o cerrar.

`RepositoryAgencyFlowTest` comprueba el caso de siete dependencias con cargas en solo tres, carpetas vacías, dependencias nuevas, archivos de ambas Direcciones, separación entre pautas, búsqueda, filtros y alcances restringidos. `CalendarAgencyFlowTest` comprueba además que la confirmación genera los árboles reales y que las fechas llegan a los ocho perfiles sin duplicarse.

## Tablero

Cada tarjeta representa una carga programada existente. No se crean tareas paralelas ni se permite arrastrarlas para cambiar estados.

| Columna | Estados |
|---|---|
| Por hacer | Programadas, abiertas, reprogramadas sin iniciar |
| En progreso | En captura, entrega parcial, observadas, reabiertas |
| En revisión | Entregadas, revisión institucional y proceso de firma |
| Validadas y cerradas | Validadas o cerradas; la validación todavía no equivale a cierre |

Los filtros incluyen dependencia, Dirección, pauta, periodo y responsable. Conteos y avance de un perfil de Dirección consideran únicamente sus entregables visibles; abrir una carga compartida no concede acceso a la otra Dirección.

Los directores consolidan su Dirección en las dependencias correspondientes al mismo código estructural. Los operativos siguen sus responsables y alcances; un alcance adicional de lectura no concede escritura. El enlace ve sus dependencias y el Director General supervisa el conjunto institucional.
