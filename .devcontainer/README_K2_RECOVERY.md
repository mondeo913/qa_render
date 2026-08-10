# SIGET K2 - Codespaces recovery fix

La configuración de Codespaces fue separada del instalador funcional de SIGET.

Objetivos:
- Evitar que una falla de Composer, npm, migración, seeder o descarga de Mailpit convierta el Codespace completo en recovery.
- Mantener PHP 8.3, Composer, Node 22, PostgreSQL, PostgreSQL client y Supervisor en la imagen base.
- Instalar Mailpit después de crear el contenedor y registrar cualquier fallo como WARN.
- Mantener el núcleo SIGET y sus scripts de aplicación sin cambios.

Para probar:
1. Crear un Codespace nuevo desde esta rama.
2. Confirmar `CODESPACES_RECOVERY_CONTAINER=false`.
3. Ejecutar `php -v`, `composer --version`, `node -v`, `npm -v`, `psql --version` y `mailpit version`.
4. Confirmar `curl http://127.0.0.1:8000/up` con HTTP 200.

El inicializador completo de SIGET es no bloqueante durante la creación del Codespace; cualquier error de aplicación queda registrado en `.codespace/logs/bootstrap-codespaces.log` y no impide abrir el entorno para diagnóstico.
