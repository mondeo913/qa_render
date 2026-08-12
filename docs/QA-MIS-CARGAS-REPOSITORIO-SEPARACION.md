# Separación de repositorios SIGET

## Operativo
`/mis-cargas` usa `MyLoadsController` y `resources/views/cargas/mis-cargas.blade.php`.

Su alcance es únicamente el usuario operativo y sus unidades/cargas accesibles. Permite captura y consulta de evidencias.

## Institucional
`/repositorio` continúa usando `RepositoryController` y `resources/views/repositorio/index.blade.php`.

Su unidad de revisión es Dependencia -> Mes -> Pauta -> Direcciones -> Evidencias, para revisión integral y reporte institucional.

Los dos flujos comparten entidades de negocio, pero no comparten vista ni controlador de listado.
