import assert from 'node:assert/strict';
import { test } from 'node:test';
import { SigetApiError, SigetClient } from './siget-client.mjs';

const json = (value, status = 200, headers = {}) => new Response(JSON.stringify(value), {
  status,
  headers: { 'Content-Type': 'application/json', ...headers },
});

test('solo HTTPS y localhost para credenciales', () => {
  assert.throws(() => new SigetClient({ baseUrl: 'http://siget.example' }), /HTTPS/);
  assert.throws(() => new SigetClient({ baseUrl: 'https://siget.example/otra/ruta' }), /api\/v1/);
  assert.throws(() => new SigetClient({ baseUrl: 'https://x:pass@siget.example' }), /credenciales/);
  assert.doesNotThrow(() => new SigetClient({ baseUrl: 'http://localhost:8000/api/v1' }));
});

test('autentica, adjunta Bearer y revoca sin guardar secreto en URLs', async () => {
  const requests = [];
  const fetchImpl = async (url, options) => {
    requests.push({ url: String(url), options });
    if (url.pathname.endsWith('/auth/token') && options.method === 'POST') {
      return json({ token_type: 'Bearer', access_token: 'token-privado', expires_at: '2026-10-20T00:00:00-06:00', user: { id: 2 } }, 201);
    }
    if (url.pathname.endsWith('/me')) return json({ data: { id: 2 } });
    return new Response(null, { status: 204 });
  };
  const client = new SigetClient({ baseUrl: 'https://siget.example/api/v1', fetchImpl });
  const info = await client.login({ email: 'usuario@siget.example', password: 'clave-privada', deviceName: 'pruebas' });
  assert.equal(info.user.id, 2);
  assert.equal(client.authenticated, true);
  assert.deepEqual(await client.me(), { data: { id: 2 } });
  assert.equal(requests[1].options.headers.get('Authorization'), 'Bearer token-privado');
  assert.equal(requests[0].options.headers.has('Authorization'), false);
  assert.equal(requests[0].options.credentials, 'omit');
  for (const item of requests) assert.doesNotMatch(item.url, /clave-privada|token-privado/);
  assert.equal(await client.logout(), null);
  assert.equal(requests[2].options.method, 'DELETE');
  assert.equal(client.authenticated, false);
  await assert.rejects(() => client.me(), { status: 401 });
});

test('422 y 429 conservan campos y Retry-After sin exponer passwords', async () => {
  const fetchImpl = async () => json({ message: 'Datos no válidos', errors: { email: ['Formato inválido'] } }, 422);
  const client = new SigetClient({ baseUrl: 'https://siget.example', fetchImpl });
  await assert.rejects(
    () => client.login({ email: 'x', password: 'clave-secreta' }),
    (error) => error instanceof SigetApiError && error.status === 422 && error.errors.email.length === 1 && !error.message.includes('clave-secreta'),
  );
  const limited = new SigetClient({
    baseUrl: 'https://siget.example',
    fetchImpl: async () => json({ message: 'Límite' }, 429, { 'Retry-After': '60' }),
  });
  await assert.rejects(() => limited.login({ email: 'x', password: 'p' }), (e) => e.status === 429 && e.retryAfter === '60');
});

test('token se limpia tras un 401 y no se reintentan operaciones automáticamente', async () => {
  let calls = 0;
  const client = new SigetClient({
    baseUrl: 'https://siget.example',
    fetchImpl: async () => {
      calls++;
      return calls === 1
        ? json({ token_type: 'Bearer', access_token: 'abc', user: { id: 1 } }, 201)
        : json({ message: 'Caducado' }, 401);
    },
  });
  await client.login({ email: 'u', password: 'x' });
  await assert.rejects(() => client.me(), { status: 401 });
  assert.equal(client.authenticated, false);
  assert.equal(calls, 2);
});

test('filtros, alcance y rutas se codifican sin permitir rutas externas', async () => {
  const urls = [];
  const client = new SigetClient({
    baseUrl: 'https://siget.example',
    fetchImpl: async (url) => {
      urls.push(url);
      return json({ token_type: 'Bearer', access_token: 't', user: {} });
    },
  });
  await client.login({ email: 'x', password: 'y' });
  await client.loads({ status: 'ABIERTA', per_page: 10, page: 2 });
  assert.equal(urls[1].searchParams.get('status'), 'ABIERTA');
  assert.equal(urls[1].searchParams.get('per_page'), '10');
  assert.equal(urls[1].pathname, '/api/v1/loads');
  await assert.rejects(() => client.get('https://evil.test'), /ruta relativa/);
  await assert.rejects(() => client.get('../me'), /ruta relativa/);
});

test('multipart deja que fetch asigne boundary y descarga binarios', async () => {
  const reqs = [];
  const client = new SigetClient({
    baseUrl: 'http://localhost:8000',
    fetchImpl: async (url, options) => {
      reqs.push(options);
      if (url.pathname.endsWith('/auth/token')) return json({ token_type: 'Bearer', access_token: 't', user: {} }, 201);
      if (url.pathname.endsWith('/evidences')) return json({ data: { id: 123 } }, 201);
      return new Response(new Uint8Array([80, 68, 70]), { status: 200, headers: { 'Content-Type': 'application/pdf' } });
    },
  });
  await client.login({ email: 'x', password: 'y' });
  const blob = new Blob(['pdf'], { type: 'application/pdf' });
  await client.uploadEvidence({ deliverableId: 4, file: blob, title: 'Prueba' });
  assert.equal(reqs[1].body instanceof FormData, true);
  assert.equal(reqs[1].headers.has('Content-Type'), false);
  assert.equal(reqs[1].body.get('deliverable_id'), '4');
  const content = await client.download('reports/pdf');
  assert.deepEqual([...new Uint8Array(content)], [80, 68, 70]);
});
