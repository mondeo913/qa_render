# Plan de pruebas QA

## A. Instalación

- Crear Codespace sin modificar archivos.
- Confirmar que `postCreateCommand` termina.
- Verificar puertos 8000 y 8025.
- Ejecutar `./codespace-test.sh`.

## B. Administrador

- Ingresar como `admin@siget.local`.
- Revisar las cuatro gráficas del dashboard.
- Crear usuario.
- Modificar permisos de un rol.
- Crear dependencia y unidad.
- Agregar requisito a una plantilla.
- Consultar auditoría.
- Abrir Centro de Operaciones.

## C. Pautas

- Descargar `public/samples/Pauta_Bienestar_QA.xlsx`.
- Importar con año 2026.
- Validar número de X.
- Confirmar.
- Revisar agrupación por fecha.
- Ver calendario.

## D. Separación por dirección

- Ingresar como `operador.monitoreo@siget.local`.
- Abrir una carga.
- Comprobar que no aparece el entregable de Producción.
- Ingresar como `operador.produccion@siget.local`.
- Comprobar la regla inversa.

## E. Evidencias

- Cargar XLSX como Monitoreo.
- Enviar a revisión.
- Ingresar como Enlace.
- Observar.
- Volver al operador.
- Cargar nueva versión.
- Enviar.
- Aprobar.
- Confirmar historial y porcentaje.

## F. Fiscalización

- Asignar fiscalizador desde una carga.
- Ingresar como Fiscalizador.
- Confirmar que solo aparecen expedientes asignados.
- Revisar evidencia.

## G. Cierre

- Validar ambos entregables.
- Completar checklist.
- Descargar expediente para firma.
- Adjuntar PDF firmado.
- Cerrar.
- Confirmar certificado.
- Abrir Mailpit y revisar aviso a Contabilidad.

## H. Reportes

- Exportar CSV.
- Exportar PDF.
- Comparar conteos con dashboard.
- Aplicar filtros por fecha.

## Criterio de aceptación

- Ningún acceso cruzado entre direcciones.
- Todas las gráficas muestran datos.
- Importación horizontal correcta.
- Evidencias versionadas.
- Historial completo.
- Cierre bloquea el expediente.
- Correo aparece en Mailpit.
- `codespace-test.sh` finaliza sin error.
