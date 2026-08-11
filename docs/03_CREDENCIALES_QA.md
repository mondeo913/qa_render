# Credenciales QA SIGET

> Solo para el entorno QA. No utilizar estas credenciales en producción.

## Credencial común

```text
Password: SigetQA_2026_Cambiar!
```

## Usuarios QA

| Rol | Usuario / correo | Contraseña |
|---|---|---|
| Administrador | `admin@siget.local` | `SigetQA_2026_Cambiar!` |
| Director General | `director.general@siget.local` | `SigetQA_2026_Cambiar!` |
| Director de Transmisión | `director@siget.local` | `SigetQA_2026_Cambiar!` |
| Director de Programación y Continuidad | `director.produccion@siget.local` | `SigetQA_2026_Cambiar!` |
| Enlace Institucional | `enlace@siget.local` | `SigetQA_2026_Cambiar!` |
| Operador de Transmisión | `operador.monitoreo@siget.local` | `SigetQA_2026_Cambiar!` |
| Operador de Programación y Continuidad | `operador.produccion@siget.local` | `SigetQA_2026_Cambiar!` |
| Fiscalizador | `fiscalizador@siget.local` | `SigetQA_2026_Cambiar!` |

## Sincronización remota del entorno QA

El repositorio contiene el comando idempotente que crea o actualiza únicamente estos usuarios QA:

```bash
php artisan siget:qa-users
```

También acepta una contraseña explícita:

```bash
php artisan siget:qa-users --password='SigetQA_2026_Cambiar!'
```

El comando asigna los roles, dependencia, unidades organizativas, permisos de lectura/escritura y estado `ACTIVE`. No reconstruye la base de datos ni elimina usuarios existentes.

## Acceso

URL local de Codespaces:

```text
http://localhost:8000/iniciar-sesion
```

Para la URL pública de Codespaces, utilizar el puerto 8000 publicado por el Codespace.
