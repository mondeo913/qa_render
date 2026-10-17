# SIGET 1.0.0 · API v1 y entrega a infraestructura

La entrega conserva el acceso web de los ocho perfiles e incorpora 37 operaciones API bajo `/api/v1`. Los usuarios necesitan un navegador, la URL institucional y una cuenta autorizada por el administrador. No hay registro público ni instalación en las computadoras de los usuarios.

La arquitectura sigue la propuesta de dos máquinas virtuales de 500 GB. La instrucción vigente del usuario establece **respaldos mensuales**, sustituyendo la periodicidad anterior del documento. El asistente genera un respaldo inicial y después habilita el calendario mensual.

## Distribución y requisitos

| Equipo | Servicios y datos | Capacidad y recursos iniciales recomendados |
|---|---|---|
| VM1 | Nginx HTTPS, Laravel web/API, PostgreSQL 16, colas, scheduler, ClamAV y respaldos | 500 GB; 4 vCPU y 8 GB de RAM como punto de partida, sujeto a medición de concurrencia y archivos |
| VM2 | Repositorio privado NFSv4 para pautas, evidencias, documentos firmados y certificados | 500 GB; 2 vCPU y 4 GB de RAM como punto de partida |
| SMTP institucional existente | Correo de seguimiento y avisos del flujo | DNS, puerto, autenticación y autorización del remitente proporcionados por infraestructura |

El kit está preparado para Ubuntu Server 24.04 LTS, systemd, Python 3, Docker Engine y Docker Compose 2.24 o posterior en VM1. VM2 requiere NFS del sistema operativo. Los scripts fijan las rutas `/srv/siget/repository`, `/srv/siget/backups` y `/etc/siget/tls`; conserve esas rutas al utilizar el asistente. Instale Docker desde el repositorio institucional aprobado o siguiendo su [documentación oficial para Ubuntu](https://docs.docker.com/engine/install/ubuntu/).

Los 500 GB de VM1 también alojan sistema, imágenes, PostgreSQL y logs: **no equivalen a 500 GB libres para respaldos**. ClamAV requiere memoria propia para sus firmas y análisis; consulte sus [requisitos oficiales](https://docs.clamav.net/manual/Installing/Docker.html). Mida y ajuste recursos con las cargas reales antes de aceptar capacidad para producción.

| Comunicación | Regla de red |
|---|---|
| Usuarios → VM1 | TCP 443; TCP 80 sólo redirige a HTTPS |
| VM1 → VM2 | TCP 2049, exclusivamente entre sus IP privadas |
| VM1 → SMTP | Puerto SMTP institucional autorizado |
| Administración → ambas VM | SSH restringido a la red de infraestructura |
| PostgreSQL y ClamAV | Puertos 5432 y 3310 sólo en la red privada de contenedores; no se publican en el host |

DNS y certificados TLS deben corresponder a la URL definitiva. No exponga NFS a los navegadores ni a la red de usuarios. Infraestructura aplica las reglas en sus firewalls e hipervisor; los scripts no reemplazan políticas de red existentes.

## Contenido de la entrega

- `deploy/Dockerfile`: imágenes de aplicación PHP 8.3, web y antivirus; dependencias fijadas con Composer y npm.
- `deploy/vm1/compose.yml`: servicios, red interna, almacenamiento persistente, comprobaciones y reinicio automático.
- `deploy/vm1/install.sh`: asistente local de seis pasos; valida NFS, certificados, configuración, instalación y respaldo inicial con restauración.
- `deploy/vm2/prepare-repository.sh` y `deploy/vm1/mount-repository.sh`: repositorio privado y montaje persistente.
- Unidades `siget.service`, `siget-watchdog.timer` y `siget-backup.timer` para systemd.
- `docs/api/openapi.json` y `docs/API_V1.md`: contrato y guía de integración.
- `SHA256SUMS` y `RELEASE_MANIFEST.json` en el paquete: identificación e integridad de la entrega.

La ejecución completa de CI genera también un paquete de imágenes probado para transferencia a VM1. El kit de código permite construirlas en infraestructura; el paquete con `images.tar.gz` permite cargarlas sin reconstruir. Ambos requieren instalar previamente Docker y los paquetes del sistema operativo. Las firmas de antivirus requieren actualización periódica por el canal aprobado de la institución.

## Instalación nueva

1. Asigne las dos VM, sus IP privadas, DNS, almacenamiento y reglas de red. Extraiga el paquete en `/opt/siget/current` en VM1 y copie el directorio `deploy/vm2` en VM2. Verifique los hashes con `sha256sum -c SHA256SUMS` desde la carpeta de la entrega.

2. En **VM2**, prepare el repositorio. Sustituya la IP de ejemplo por la IP privada real de VM1:

   ```bash
   sudo bash deploy/vm2/prepare-repository.sh 10.0.0.11
   ```

   El script conserva el identificador existente del repositorio, utiliza UID/GID 33 para la aplicación y exporta sólo a VM1 con `root_squash`. Permita TCP 2049 desde esa IP. La configuración de [NFS está documentada por Ubuntu](https://ubuntu.com/server/docs/how-to/networking/install-nfs/).

3. En **VM1**, monte VM2 y coloque el certificado institucional:

   ```bash
   cd /opt/siget/current
   sudo bash deploy/vm1/mount-repository.sh 10.0.0.12
   sudo install -d -m 0700 /etc/siget/tls
   # Infraestructura coloca fullchain.pem y privkey.pem en /etc/siget/tls.
   sudo chmod 0600 /etc/siget/tls/privkey.pem
   ```

   La IP del segundo comando corresponde a VM2. El asistente comprueba que existe un montaje NFSv4 real; un directorio local no sustituye a VM2. Por `root_squash`, las verificaciones del repositorio se realizan como UID/GID 33, igual que la aplicación.

4. Ejecute el asistente en **VM1**:

   ```bash
   cd /opt/siget/current
   sudo bash deploy/vm1/install.sh
   ```

   Solicita URL HTTPS, SMTP, remitente, soporte, contabilidad y el día del respaldo mensual. El día predeterminado es el **1 de cada mes a las 02:00, America/Mexico_City**; se puede elegir del 1 al 28. Genera claves aleatorias, separa el usuario PostgreSQL de la aplicación del administrador del motor y guarda la configuración con permisos 0600. La clave administrativa de PostgreSQL no se entrega al contenedor web.

   Después de migrar pide nombre, correo y contraseña del primer administrador. Producción exige al menos 12 caracteres con mayúscula, minúscula, número y símbolo. Crea los catálogos institucionales y **un solo administrador inicial**; no crea pautas, evidencias ni cuentas demostrativas. Los comandos y seeders QA rechazan producción.

   Al finalizar habilita servicios, watchdog y timer mensual, ejecuta un respaldo inicial y restaura una copia de prueba en destinos aislados. Si alguno de esos pasos falla, no informa que la instalación terminó correctamente.

5. Si una instalación quedó incompleta, corrija la causa indicada y reanude conservando las claves:

   ```bash
   sudo bash deploy/vm1/install.sh --resume
   ```

   Un administrador existente conserva su identidad y contraseña. Para comprobar sólo prerrequisitos use `sudo bash deploy/vm1/install.sh --check`.

6. Entre por la URL HTTPS como administrador y dé de alta las personas autorizadas. La opción **Perfil general para todas las dependencias** permite usar una cuenta por persona; los operativos mantienen su dirección y asignación de responsable. Al agregar otra dependencia, sus dos direcciones y plantilla se preparan y los perfiles generales reciben el alcance correspondiente. Un fiscalizador sólo ve expedientes que le hayan sido asignados.

Si existe un balanceador institucional, configure sus IP de confianza en `TRUSTED_PROXIES` antes de aceptar tráfico. Por defecto producción no confía en cabeceras de proxies arbitrarios. Configure y valide el SMTP real mediante una notificación de seguimiento con destinatarios de prueba autorizados; CI no envía correo institucional.

## Operación y recuperación de servicios

```bash
sudo systemctl status siget.service --no-pager
sudo systemctl list-timers 'siget-*' --no-pager
sudo systemctl start siget.service
sudo systemctl start siget-watchdog.service
sudo journalctl -u siget-watchdog.service -n 80 --no-pager
```

Para ver contenedores sin imprimir las claves:

```bash
cd /opt/siget/current
sudo docker compose --env-file deploy/vm1/.env.production -f deploy/vm1/compose.yml ps
curl --fail https://siget.institucion.gob.mx/ready
```

Docker reinicia procesos terminados inesperadamente; el watchdog comprueba y recupera servicios cada minuto. Las operaciones de mantenimiento toman un bloqueo compartido para que el watchdog no reactive escritores durante un respaldo. Un reinicio del host habilita nuevamente los servicios y espera el montaje privado.

`/ready` comprueba PostgreSQL, identificador del repositorio y antivirus; no expone rutas ni credenciales. Una pérdida del repositorio bloquea el acceso y las cargas. Un antivirus caído o un archivo detectado impide incorporar ese archivo. NFS utiliza un montaje `hard` para impedir escrituras en un sustituto local; una caída de VM2 requiere restablecer la red o VM2.

## Respaldos MENSUALES

La política implementada es **un respaldo completo al mes**, además del inicial y las ejecuciones manuales que infraestructura solicite. No se instala ninguna ejecución quincenal. Cada respaldo es independiente e incluye:

- PostgreSQL completo, con usuarios, permisos, pautas, estados, metadatos y auditoría.
- Repositorio privado completo, con pautas Excel, versiones de evidencias, firmados y certificados.
- Configuración operativa sin secretos, manifiesto de archivos, tamaños y hashes SHA-256.

El respaldo detiene solicitudes y escritores mediante mantenimiento y parada de app, worker y scheduler; comprueba espacio; genera y verifica archivos; ejecuta `pg_restore` en una base `siget_restore_*` vacía; restaura los archivos a un directorio aislado; compara conteos de tablas con el manifiesto; elimina la copia temporal después de una prueba correcta y reactiva el servicio. Cada copia usa una carpeta UUID en `/srv/siget/backups` y queda registrada en el Centro de Operaciones.

Antes de crearla exige espacio para el tamaño del repositorio más el tamaño estimado de la base, un margen del 30 % y una reserva predeterminada de 5 GiB. La prueba de restauración necesita además espacio temporal para otra copia y su base. Si falta espacio, conserva las copias previas y registra el fallo; no crea un respaldo incompleto como exitoso. No se eliminan respaldos existentes de forma automática: infraestructura debe acordar la retención y medir que quepan en el espacio real de VM1. Un repositorio de VM2 próximo a 500 GB no cabe necesariamente como copia en VM1.

Los respaldos sólo se montan en el contenedor de mantenimiento; el servicio web y los workers no acceden a esa carpeta. Los logs de contenedor rotan por tamaño y los de Laravel diariamente con retención de 30 días; la auditoría de negocio permanece en PostgreSQL y entra en el respaldo mensual.

```bash
# Ejecución manual completa, incluida la prueba de restauración:
sudo systemctl start siget-backup.service

# Calendario y resultado:
sudo systemctl list-timers siget-backup.timer --no-pager
sudo journalctl -u siget-backup.service -n 120 --no-pager
```

SIGET registra incidentes si falla la copia o no hay un respaldo verificado en 35 días. Infraestructura debe conectar el estado de las unidades systemd y los registros al monitoreo institucional, incluyendo fallos del host o de red que impidan ejecutar PHP. El punto de recuperación es el último respaldo mensual completado; mida y registre la duración real de copia y restauración. Registre también si ambas VM comparten host físico, pues su disponibilidad depende de esa infraestructura.

### Verificación y restauración controlada

Desde VM1, verifique primero una carpeta de respaldo, sin modificar datos:

```bash
cd /opt/siget/current
sudo docker compose --env-file deploy/vm1/.env.production -f deploy/vm1/compose.yml \
  run --rm --no-deps -T maintenance php artisan siget:backup-restore /backups/UUID_DEL_RESPALDO
```

Para un simulacro independiente, infraestructura crea una base vacía cuyo nombre comience con `siget_restore_`, con propietario igual a `DB_USERNAME`, y un directorio de archivos vacío accesible por UID/GID 33 dentro de `/srv/siget/backups/.restore-tests/`. Luego ejecuta:

```bash
sudo docker compose --env-file deploy/vm1/.env.production -f deploy/vm1/compose.yml \
  run --rm --no-deps -T maintenance php artisan siget:backup-restore /backups/UUID_DEL_RESPALDO \
  --execute --target-database=siget_restore_simulacro \
  --target-repository=/backups/.restore-tests/simulacro
```

El comando rechaza reemplazar la base configurada, una base con tablas, un directorio ocupado, hashes ausentes o alterados y rutas peligrosas en el archivo. No cambia la configuración activa. Para recuperación real: mantenga SIGET detenido, conserve lo recuperable de las VM anteriores, restaure primero a esos destinos aislados, valide conteos y hashes, reponga los archivos en VM2 como UID/GID 33 conservando `.siget-repository-id`, y cambie la base de la configuración hacia la copia verificada. Reinicie los servicios y complete la aceptación por perfiles antes de habilitar acceso.

`APP_KEY`, contraseñas, `.env.production` y certificados no forman parte del archivo de configuración del respaldo. Infraestructura debe custodiarlos por separado en su mecanismo institucional y recuperarlos antes de iniciar la aplicación. No genere otra `APP_KEY` al recuperar una instalación existente.

## Actualizaciones

Conserve `.env.production`, certificados, volúmenes de PostgreSQL y almacenamiento. Incorpore el código de la entrega aprobada en la ruta de instalación manteniendo esa configuración y ejecute:

```bash
cd /opt/siget/current
sudo bash deploy/vm1/upgrade.sh
```

El script reconstruye imágenes, aplica migraciones y limpia cachés dentro de una ventana de mantenimiento. No ejecuta `migrate:fresh`, no carga datos QA y no borra volúmenes. Valide un respaldo recuperable antes de cambiar de versión. Si una migración falla, el script devuelve error; revise el registro antes de aceptar el cambio. Una reversión de aplicación que requiera revertir estructura se realiza con la copia previamente validada, no borrando la base.

## Aceptación que debe firmar infraestructura

| Comprobación | Evidencia que debe registrar en sus VM |
|---|---|
| DNS, HTTPS y cadena TLS | URL definitiva válida desde la red autorizada |
| Red y NFS | IP privadas, exportación sólo a VM1, montaje persistente y UID/GID 33 |
| Usuarios | Administrador inicial; personas reales con perfil, dirección y alcance correctos |
| Flujo | Pauta → fechas por dependencia → ambas direcciones → revisiones → firma → cierre; reapertura conserva historial |
| Aislamiento | Un operativo no modifica entregables ajenos; una dirección no descarga archivos de la otra; fiscalización requiere asignación |
| SMTP | Aviso real recibido por destinatarios de aceptación autorizados |
| Antivirus | Archivo de prueba antivirus rechazado y archivo válido aceptado |
| Disponibilidad | Reinicio de contenedor y de ambas VM; prueba controlada de pérdida y recuperación de NFS |
| Respaldo mensual | Timer habilitado, copia inicial verificada, restauración aislada, espacio y retención documentados |

CI ejecuta pruebas funcionales con SQLite y PostgreSQL, navegación de los ocho perfiles y sus vistas responsivas, construcción real de imágenes y aceptación HTTPS/API/antivirus/respaldo/restauración. El repositorio privado se representa con un directorio aislado en CI; NFS real, IP, firewall, correo y reinicios de las VM se aceptan en el entorno de infraestructura con esta tabla.
