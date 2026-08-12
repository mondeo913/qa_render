# SIGET — Repositorio y expediente institucional

## Regla funcional

El repositorio se presenta primero por **dependencia**. Al abrir una dependencia se integran todos sus expedientes programados y las evidencias de todas sus direcciones. La captura de archivos puede continuar siendo por entregable/dirección, pero la validación institucional, la generación del expediente para firma y el cierre se realizan sobre el expediente completo.

## Validación antes de firma/cierre

El expediente requiere que los requisitos obligatorios de la pauta:

- estén representados por las direcciones de la dependencia;
- tengan evidencia y archivos dentro de mínimos/máximos;
- respeten extensiones y tamaño configurados por la pauta;
- estén validados cuando el requisito lo exige;
- tengan el entregable validado;
- respeten la fecha programada de entrega;
- y que el periodo programado de la carga haya concluido.

La generación del expediente para firma queda bloqueada hasta que la validación integral sea satisfactoria. El cierre requiere además el documento firmado.

## Compatibilidad de descargas

`NoCacheAuthenticated` escribe las cabeceras directamente sobre el `HeaderBag` de Symfony para soportar `StreamedResponse`/descargas de Laravel 12.
