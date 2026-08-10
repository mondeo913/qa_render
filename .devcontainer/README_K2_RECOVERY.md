# SIGET K2 - Corrección de Codespaces

Esta rama corrige la configuración del entorno de desarrollo sin modificar el núcleo funcional de SIGET.

## Objetivos
- Evitar que un fallo de Composer, npm, migraciones, seeders o descarga de Mailpit ponga el Codespace completo en recovery.
- Mantener PHP 8.3, Composer, Node 22, PostgreSQL, cliente PostgreSQL y Supervisor en la imagen.
- Instalar Mailpit después de construir la imagen y registrar fallos como WARN.
- Mantener el instalador funcional de SIGET separado del ciclo de construcción del contenedor.

## Prueba
1. Crear un Codespace nuevo desde esta rama.
2. Confirmar `CODESPACES_RECOVERY_CONTAINER=false`.
3. Confirmar `php -v`, `composer --version`, `node -v`, `npm -v`, `psql --version` y `mailpit version`.
4. Confirmar `curl http://127.0.0.1:8000/up` con HTTP 200.
5. Si el bootstrap de aplicación falla, revisar `.codespace/logs/bootstrap-codespaces.log`; el contenedor debe permanecer utilizable.
