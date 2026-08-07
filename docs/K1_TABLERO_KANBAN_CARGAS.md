# K1 - Tablero Kanban de cargas por dependencia

## Objetivo

Incorporar una vista operativa para las Direcciones de Transmisión y de Programación y Continuidad, alimentada exclusivamente por las cargas programadas que genera la pauta confirmada y el calendario inteligente de SIGET.

## Regla de negocio principal

El tablero no crea tareas paralelas y no permite arrastrar tarjetas. Cada tarjeta es una `scheduled_load` existente y su columna se calcula a partir del estado técnico del expediente.

## Columnas visuales

- **Por hacer:** programadas, abiertas, reprogramadas o todavía no iniciadas.
- **En progreso:** en captura, entregas parciales, observadas o reabiertas.
- **En revisión:** entregadas, en revisión institucional, listas para firma o pendientes de documento firmado.
- **Validadas y cerradas:** validadas o cerradas institucionalmente.

## Responsabilidades

- Los operativos capturan, corrigen y envían.
- El Enlace Institucional revisa, observa, valida y cierra.
- Los directores consultan el tablero de su dirección sin permisos de revisión o cierre.
- El Director General consulta el tablero institucional consolidado.

## Seguridad

La consulta pasa por `AccessScopeService`. Las relaciones de entregables se filtran nuevamente para impedir que un usuario de una dirección vea requisitos, responsables o evidencias de la otra dirección.

## Componentes agregados

- `LoadBoardController`
- `LoadBoardService`
- `resources/views/cargas/tablero.blade.php`
- permiso `scheduled_load.board`
- migración no destructiva de permiso
- acceso de menú por rol
- estilos responsivos del tablero
- pruebas unitarias y de aislamiento

## Filtros

- texto libre
- dirección
- dependencia
- periodo
- solamente cargas asignadas al operativo

## Indicadores por dependencia

- total
- por hacer
- en progreso
- en revisión
- validadas y cerradas
- vencidas
- porcentaje de avance visible
- próximo vencimiento

## Criterio de avance visible

Para usuarios con alcance por dirección, el porcentaje se calcula únicamente con los entregables visibles de esa dirección. Esto evita mostrar datos operativos de otra unidad aunque la carga programada sea compartida.

## Alcance multi-dependencia

Los directores y operativos pueden consultar varias dependencias cuando sus registros `user_scopes` contienen permisos de lectura para las agencias y unidades correspondientes. Si no existen alcances explícitos, SIGET conserva el comportamiento heredado y utiliza la dependencia y unidad principales del usuario como respaldo.
