# SIGET — Repositorio y tablero vigentes

## Captura y revisión

`/mis-cargas` utiliza su propio controlador y vista para el trabajo del operativo. `/repositorio` presenta el expediente institucional. Comparten entidades de negocio, pero no el listado de captura.

La agrupación de consulta es Dependencia → Mes programado → Pauta → Direcciones → Evidencias → Archivos. Las cargas por fecha generadas al confirmar la pauta conservan sus entregables, revisión y cierre; el agrupamiento visual no crea expedientes paralelos ni permite cerrar una evidencia aislada como expediente institucional.

La validación considera los requisitos obligatorios, ambas Direcciones, cantidades, formatos, tamaños, versión vigente, aprobaciones y fechas. Las responsabilidades y excepciones de plazo se detallan en [el flujo controlado](FLUJO_SIGET_CONTROLADO.md).

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
