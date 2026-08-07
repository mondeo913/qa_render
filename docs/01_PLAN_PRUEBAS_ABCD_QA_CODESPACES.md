# Plan resumido de pruebas ABCD QA

## A. Acceso y seguridad

- Inicio de sesión del administrador.
- Validación de roles y permisos.
- Aislamiento por ámbito.
- Acceso a menús según rol.

## B. Calendario

- Importación horizontal por marcas X.
- Vista previa.
- Confirmación de importación.
- Suspensiones y reprogramaciones.
- Disponibilidad de fechas.

## C. Evidencias

- Alta de carga programada.
- Carga de documentos.
- Versiones.
- Observaciones.
- Correcciones.
- Aprobación.
- Fiscalización.

## D. Cierre

- Checklist.
- Documento firmado.
- Cierre institucional.
- Constancia.
- Aviso a Contabilidad.

## Automatización

Ejecute:

```bash
bash PROBAR_ABCD_QA.sh
```

El script combina:

- PHPUnit.
- Estado de datos QA.
- Endpoint de salud.
- Inicio de sesión.
- Mailpit.
- Estado de procesos.
