# Guía paso por paso

## 1. Crear un repositorio privado

En GitHub:

1. Seleccione **New repository**.
2. Nombre sugerido: `siget-abcd-qa`.
3. Marque **Private**.
4. Agregue un archivo README.
5. Cree el repositorio.

## 2. Crear el Codespace

1. Abra el repositorio.
2. Seleccione **Code**.
3. Abra la pestaña **Codespaces**.
4. Seleccione **Create codespace on main**.
5. Espere a que aparezca el editor en el navegador.

## 3. Subir el ZIP

Arrastre el archivo:

```text
SIGET_ABCD_CODESPACES_QA_AUTOMATICO_V2_0.zip
```

hacia el explorador de archivos del Codespace.

## 4. Importar SIGET

Abra la terminal y ejecute:

```bash
unzip -q SIGET_ABCD_CODESPACES_QA_AUTOMATICO_V2_0.zip
bash SIGET_ABCD_CODESPACES_QA_AUTOMATICO_V2_0/IMPORTAR_EN_REPOSITORIO_CODESPACES.sh
```

## 5. Reconstruir el contenedor

1. Presione `Ctrl+Shift+P`.
2. Escriba `Codespaces: Rebuild Container`.
3. Seleccione esa opción.
4. Espere la instalación automática.

Durante la instalación aparecerán diez etapas:

```text
PostgreSQL
Configuración .env
Composer
npm
APP_KEY
Migraciones y datos QA
Almacenamiento
Vite
Servicios
Verificación
```

## 6. Acceso

El puerto 8000 se abrirá automáticamente.

Credenciales:

```text
admin@siget.local
SigetQA_2026_Cambiar!
```

Para ver las direcciones:

```bash
bash MOSTRAR_URLS.sh
```

## 7. Ejecutar las pruebas ABCD QA

```bash
bash PROBAR_ABCD_QA.sh
```

El reporte se guardará en:

```text
ULTIMO_REPORTE_PRUEBAS_ABCD_QA.txt
```

## 8. Revisar Mailpit

```bash
bash MOSTRAR_URLS.sh
```

Abra la dirección de Mailpit que termina en el puerto 8025.

## 9. Detener para ahorrar cuota

En GitHub, detenga el Codespace cuando termine las pruebas. Al abrir el mismo Codespace posteriormente, SIGET volverá a iniciar automáticamente.
