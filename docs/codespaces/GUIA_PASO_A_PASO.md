# Codespaces — Guía vigente de SIGET

## Crear o actualizar

1. Abrir `mondeo913/qa_render` en GitHub, rama `main`.
2. Seleccionar **Code → Codespaces → Create codespace on main**.
3. En un Codespace existente, actualizar primero el código sin descartar cambios propios y ejecutar **Codespaces: Rebuild Container** desde la paleta de comandos.
4. Esperar a que termine la instalación y abrir el puerto 8000. Mailpit utiliza el 8025 para correo de prueba.

No subir ZIP de versiones anteriores ni ejecutar instaladores de otras entregas.

## Una sola ruta de arranque

`devcontainer.json` usa `Dockerfile.k2` para preparar PHP, PostgreSQL, Supervisor y Mailpit; la característica Node proporciona Node 22.

| Momento | Entrada |
|---|---|
| Crear el contenedor | `.devcontainer/install-siget.sh` |
| Iniciar el Codespace | `.devcontainer/qa-runtime.sh` |
| Volver a adjuntar el editor | `.devcontainer/qa-runtime.sh` |
| Recuperación manual | `bash INICIAR_SIGET.sh` o `bash RECUPERAR_SIGET.sh` |

La recuperación manual delega al mismo runtime. Ese runtime usa el instalador si falta la marca de instalación, verifica el build del commit, recupera PostgreSQL, inicia Supervisor y comprueba la respuesta web y Mailpit. La recuperación de PID obsoleto se centraliza en `lib-siget.sh`; no hay un segundo script de reparación.

## Servicios y persistencia

- Aplicación: puerto 8000, `/iniciar-sesion`.
- Correo QA Mailpit: puerto 8025.
- PostgreSQL: instancia local privada en el contenedor.
- Cola y scheduler: procesos de Supervisor.
- Watchdogs: recuperación de PostgreSQL y de la pila de servicios; no son archivos antiguos prescindibles.
- Datos de PostgreSQL, Mailpit y logs: `.codespace/`, fuera del control de versiones.

La recuperación de un entorno ya instalado no reconstruye la base. `REINICIAR_DATOS_QA.sh --confirmar` existe para reinicializar deliberadamente un entorno de demostración y elimina sus datos; no forma parte del arranque ni de la actualización normal.

## Acceso y comprobación

Ejecutar `bash MOSTRAR_URLS.sh`. Consultar `.codespace/ACCESOS.txt` para las cuentas QA y comprobar usuarios y roles en Administración. No utilizar credenciales de demostración en producción.

```sh
make verify
make test
make diagnose
```

`make test` ejecuta PHPUnit, estado de datos QA y comprobaciones de la aplicación y Mailpit. Para la validación integral de QA con migraciones, permisos, pruebas, Vite y cachés, ejecutar `make validate`.

Los reportes de verificación se generan fuera del código versionado. Si el entorno no inicia, revisar `.codespace/logs/` con el diagnóstico; no importar otro paquete encima del repositorio.

## Alcance de la validación

GitHub Actions prueba código, compilación y el flujo institucional con PostgreSQL. Una reconstrucción real de Codespaces y su prueba manual siguen siendo necesarias para aceptar el entorno del usuario. Los servicios de este contenedor son de QA; no sustituyen el wizard ni la infraestructura de las máquinas virtuales.
