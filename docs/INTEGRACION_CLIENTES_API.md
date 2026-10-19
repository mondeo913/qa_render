# Integración de clientes externos — SIGET API v1

SIGET ya expone el contrato [OpenAPI 3.0](api/openapi.json) en la ruta `/api/v1`.
Este paquete agrega un **cliente Node.js 22+** para integraciones de servidor a servidor, sin sustituir la autenticación del portal Laravel.

## Preparación

- Trabaje desde un servidor autorizado, con HTTPS y una cuenta SIGET activa a la que se hayan asignado permisos mínimos.
- No introduzca credenciales de SIGET en JavaScript servido al navegador, repositorios, ficheros de configuración públicos o mensajes de error. Configure los secretos en el gestor de secretos del servidor.
- La emisión del token API y la sesión de navegador son mecanismos diferentes. El portal usa la cookie de sesión y CSRF Laravel. El cliente externo usa exclusivamente el token Bearer y no maneja cookies.
- Los datos, los alcances por Dirección y las transiciones de los expedientes siguen gobernados por SIGET: el cliente no modifica permisos ni el backend.

## Ejemplo básico

```js
import { SigetClient, SigetApiError } from './integrations/node/siget-client.mjs';

const api = new SigetClient({ baseUrl: process.env.SIGET_API_URL });
try {
  await api.login({
    email: process.env.SIGET_API_EMAIL,
    password: process.env.SIGET_API_PASSWORD,
    deviceName: 'integracion-institucional',
  });

  const usuario = await api.me();       // GET /api/v1/me
  const dependencias = await api.agencies();
  const cargas = await api.loads({ per_page: 25, page: 1 });
  console.log(usuario.data.id, dependencias.data.length, cargas.meta.total);
} catch (error) {
  if (error instanceof SigetApiError) {
    console.error('Respuesta SIGET:', error.status, error.message, error.retryAfter);
  } else {
    console.error('Error de red o de integración');
  }
} finally {
  await api.logout(); // DELETE /api/v1/auth/token
}
```

## Operaciones de API

`api.request(method, path, options)` admite todas las rutas relativas del contrato OpenAPI sin fijar su origen desde entrada no confiable. El token se adjunta automáticamente, **se conserva únicamente en memoria** y se limpia al recibir 401. No hay reintentos automáticos en operaciones de escritura (importar, enviar, revisar, cerrar), porque el servidor no establece idempotencia genérica.

```js
const eventos = await api.calendar({ start: '2026-10-01', end: '2026-11-01' });
// Para importar: api.post('imports', new FormData()...)
// Para revisar: api.post('evidences/123/reviews', { decision:'APROBADO', comments:'Conforme' })
// Para descarga: const bytes = await api.download('reports/pdf', { params: { ... } });
```

En Node, una evidencia multipart se envía con `api.uploadEvidence({ deliverableId, file, title })`, donde `file` es un `Blob` o `File` compatible con `FormData`. No establezca manualmente `Content-Type: multipart/form-data`, ya que Fetch genera el separador boundary. Los adjuntos XLSX y los PDF binarios deben utilizarse sin convertir a JSON.

`SigetApiError` proporciona `status`, `errors` (errores de validación), `retryAfter` y `requestId` si el servidor o proxy lo envía. 401 obliga a iniciar sesión de nuevo; 403 significa falta de permiso o alcance; 422 es validación o regla de flujo; 429 requiere respetar Retry-After. Nunca registre el token o la contraseña.

## Pruebas automáticas

```bash
node --test integrations/node/siget-client.test.mjs
```

Las pruebas usan un transporte simulado: comprueban seguridad de URLs, Bearer, logout, revocación local ante 401, parámetros, errores, multipart y descargas. No requieren cuentas reales. CI ejecuta estas pruebas además de la batería PHP ya existente.

## Verificación integrada sobre un entorno QA existente

La siguiente verificación **sí contacta al servidor configurado**: inicia sesión, llama a `/me` y `/agencies` y revoca el token. No crea pautas ni altera expedientes.

```bash
export SIGET_API_URL='https://tu-servidor-institucional/api/v1'
export SIGET_API_EMAIL='correo@institucion'
# Lea la contraseña de forma interactiva para no dejarla en el historial:
read -rsp 'Contraseña de QA: ' SIGET_API_PASSWORD; echo
export SIGET_API_PASSWORD
node integrations/node/smoke.mjs
unset SIGET_API_PASSWORD
```

El test remoto depende de contar con el endpoint DNS/HTTPS y credenciales de prueba; no se ejecuta automáticamente contra producción. No utilice el entorno productivo para ensayos de integración funcional que cambien datos.

## Despliegue y criterios de aceptación

1. Desplegar la misma revisión del código en VM1 o actualizar el Codespace QA y aplicar la guía de [infraestructura](ENTREGA_INFRAESTRUCTURA_API.md).
2. Confirmar el estado de `GET /ready` (disponibilidad) y el acceso al portal `/iniciar-sesion`; **`/ready` no requiere Bearer**, pero no expone datos.
3. Ejecutar el smoke anterior con una cuenta de prueba autorizada, nunca con el administrador productivo.
4. Revisar CI del mismo commit, revocación de token, permisos por dependencia y acceso al repositorio privado.
5. Validar DNS, HTTPS, SMTP, antivirus, VM2 y respaldo/restauración con infraestructura. CI y el cliente Node no sustituyen la aceptación en servidores reales.

No se habilitan SSO, acceso público ni API para gestionar respaldos. La generación del oficio institucional final sigue pendiente de la plantilla oficial.
