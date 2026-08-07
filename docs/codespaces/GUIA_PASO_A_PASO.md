# Guía paso a paso — SIGET en GitHub Codespaces

## 1. Crear el repositorio

Cree un repositorio privado en GitHub. No agregue README ni plantilla para evitar conflictos.

## 2. Cargar SIGET

Descomprima el paquete. Suba todo su contenido a la raíz del repositorio. Debe observar:

```text
.devcontainer/
app/
database/
resources/
routes/
artisan
composer.json
package.json
README.md
```

No cree una carpeta adicional llamada `SIGET_CODESPACES_QA_COMPLETO_V1` dentro del repositorio.

## 3. Crear el Codespace

En GitHub:

1. Abra el repositorio.
2. Pulse **Code**.
3. Abra la pestaña **Codespaces**.
4. Pulse **Create codespace on main**.

GitHub leerá `.devcontainer/devcontainer.json`, construirá PHP 8.3 y levantará PostgreSQL y Mailpit.

## 4. Esperar la preparación

El proceso automático:

1. Instala Composer.
2. Instala Node 22.
3. Instala dependencias PHP y frontend.
4. Crea `.env`.
5. Espera PostgreSQL.
6. Ejecuta migraciones y seeders.
7. Compila Vite.
8. Inicia web, queue y scheduler.
9. Reenvía los puertos 8000 y 8025.

## 5. Abrir SIGET

Abra la pestaña **Ports**. Localice:

- `8000 — SIGET QA`
- `8025 — Correo QA - Mailpit`

Pulse el icono del globo en el puerto 8000.

## 6. Credenciales

Abra:

```text
.codespace/ACCESOS.txt
```

La contraseña inicial para todos los usuarios es:

```text
SigetQA_2026_Cambiar!
```

## 7. Probar

En la terminal:

```bash
./codespace-test.sh
```

Debe finalizar con:

```text
PRUEBAS QA BASICAS: APROBADAS
```

## 8. Ver correos

Abra el puerto 8025. Los correos generados por SIGET aparecerán en Mailpit; no se enviarán a destinatarios externos.

## 9. Reiniciar datos

```bash
./codespace-reset.sh
```

Esto reconstruye la base QA con los usuarios y cargas demostrativas.

## 10. Diagnóstico

```bash
./codespace-diagnose.sh
```

El reporte queda en `.codespace/diagnostico-FECHA-HORA.txt`.

## Problemas frecuentes

### No abre el puerto 8000

```bash
make start
make diagnose
```

### Error de base de datos

```bash
pg_isready -h db -U siget -d siget_qa
php artisan migrate:status
```

### Interfaz sin estilos

```bash
npm install
npm run build
php artisan optimize:clear
```

### Worker detenido

```bash
supervisorctl -c .devcontainer/supervisord.conf restart siget-queue
```
