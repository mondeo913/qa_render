# Bloque P0 - Estabilización QA, parser y roles específicos

## Objetivo

Certificar el ambiente QA antes del tablero Kanban y aplicar cuatro correcciones de línea base:

1. Incorporar `pdo_sqlite` al devcontainer para que PHPUnit pueda usar SQLite en memoria.
2. Corregir el parser horizontal para pautas parciales y para el archivo de muestra institucional.
3. Retirar la revisión operativa de los directores.
4. Crear los roles específicos de ambas direcciones.

## Roles creados

- `DIRECTOR_TRANSMISION`
- `DIRECTOR_PROGRAMACION_CONTINUIDAD`
- `OPERADOR_TRANSMISION`
- `OPERADOR_PROGRAMACION_CONTINUIDAD`

Los roles `DIRECTOR` y `OPERADOR` permanecen temporalmente para compatibilidad y migración.

## Segregación confirmada

- Operativos: capturan, adjuntan y envían.
- Enlace Institucional: revisa, observa, valida y cierra.
- Directores: supervisan indicadores, calendario, repositorio y cumplimiento de su dirección.
- Director General: supervisión global.
- Fiscalizador: conserva su revisión especializada asignada, sin cierre institucional.

## Parser

La detección ya no exige al menos siete días. Se usa una puntuación de candidatos para emparejar una fila de meses con una fila de tres o más días ubicada hasta tres filas después. Esto soporta pautas parciales, archivos de prueba y el archivo `public/samples/Pauta_Bienestar_QA.xlsx`.

## Ejecución en Codespaces

Reconstruir el contenedor para instalar `pdo_sqlite`:

```bash
# Command Palette: Codespaces: Rebuild Container
```

Después ejecutar:

```bash
./P0_CERTIFICAR_QA.sh
```

El script ejecuta migraciones, seeders de permisos, pruebas específicas, suite completa, compilación Vite y verificación de servicios.

## Migración no destructiva

La migración `2026_08_05_000001_integrate_p0_roles_and_permissions.php`:

- crea los cuatro roles;
- asigna permisos de supervisión o carga;
- elimina `evidence.review` de directores;
- migra usuarios heredados según `DIR_A` y `DIR_B`;
- adapta los requisitos de plantillas;
- incluye rollback hacia los roles heredados.
