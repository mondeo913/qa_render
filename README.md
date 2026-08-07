# SIGET K2 — Núcleo original + alcance integrado

Paquete acumulado para instalar directamente en la raíz de un repositorio de GitHub y ejecutar mediante GitHub Codespaces.

## Identidad institucional

- **SIGET**
- **Sistema de Gestión de Evidencias de Transmisión**

## Contenido integrado

- Núcleo original de SIGET adaptado a QA/Codespaces.
- P0: estabilización del parser de pautas, cuatro roles específicos y permisos corregidos.
- K1: tablero Kanban de cargas por dependencia.
- K2: integración visual y funcional acumulativa:
  - tema claro, oscuro y automático;
  - autenticación renovada y recuperación de contraseña;
  - dashboards diferenciados por rol;
  - calendario inteligente con próximos eventos;
  - centro de notificaciones con filtros;
  - repositorio tipo File Manager;
  - bandeja institucional de revisión;
  - identidad SIGET corregida en la interfaz y reportes.

## Responsabilidades funcionales

- Los operativos capturan, corrigen y envían las cargas.
- El Enlace Institucional revisa, observa, devuelve, valida y cierra.
- Los directores supervisan solamente su dirección.
- El Director General supervisa el cumplimiento institucional consolidado.
- El Fiscalizador conserva la revisión especializada definida por el núcleo.

## Instalación en GitHub Codespaces

1. Crea un repositorio nuevo o una rama de integración.
2. Descomprime el ZIP K2.
3. Sube el contenido interno directamente a la raíz del repositorio.
4. Confirma que existan `.devcontainer/`, `app/`, `database/`, `resources/`, `routes/`, `artisan`, `composer.json` y `package.json`.
5. Crea o reconstruye el Codespace.
6. Espera a que termine `.devcontainer/install-siget.sh`.
7. Ejecuta:

```bash
chmod +x K2_CERTIFICAR_COMPLETO.sh
./K2_CERTIFICAR_COMPLETO.sh
```

## Puertos QA

- `8000`: SIGET
- `8025`: Mailpit

## Estado de esta entrega

La integración de código y la compilación de recursos Vite fueron validadas durante el empaquetado. La certificación completa de PostgreSQL, migraciones, seeders, PHPUnit, permisos por rol y flujo de extremo a extremo debe ejecutarse en el Codespace reconstruido mediante `K2_CERTIFICAR_COMPLETO.sh`.

El paquete no contiene `.env`, `vendor`, `node_modules`, una base física de PostgreSQL, secretos reales ni evidencias institucionales.
