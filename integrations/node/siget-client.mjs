/**
 * SIGET API v1 — cliente de integración para Node.js 22+.
 * Diseñado para un proceso de confianza (backend/servicio), NO para código
 * ejecutado en el navegador: los tokens Bearer permanecen solo en memoria.
 */
export class SigetApiError extends Error {
  constructor(message, { status = 0, errors = null, retryAfter = null, requestId = null } = {}) {
    super(message);
    this.name = 'SigetApiError';
    this.status = status;
    this.errors = errors;
    this.retryAfter = retryAfter;
    this.requestId = requestId;
  }
}

export class SigetClient {
  #token = null;
  #fetch;
  #baseUrl;
  #timeoutMs;

  constructor({ baseUrl, fetchImpl = globalThis.fetch, timeoutMs = 15000 } = {}) {
    if (typeof baseUrl !== 'string' || !baseUrl.trim()) throw new TypeError('Se requiere baseUrl.');
    const url = new URL(baseUrl);
    const isLocal = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);
    if (url.protocol !== 'https:' && !(isLocal && url.protocol === 'http:')) {
      throw new TypeError('La API requiere HTTPS salvo en localhost.');
    }
    if (url.username || url.password || url.search || url.hash) throw new TypeError('baseUrl no debe incluir credenciales ni parámetros.');
    const prefix = url.pathname.replace(/\/+$/, '');
    if (prefix !== '' && prefix !== '/api/v1') throw new TypeError('baseUrl debe apuntar al origen o a /api/v1.');
    if (typeof fetchImpl !== 'function') throw new TypeError('fetchImpl debe ser una función.');
    if (!Number.isInteger(timeoutMs) || timeoutMs < 100 || timeoutMs > 120000) throw new RangeError('timeoutMs debe estar entre 100 y 120000.');
    this.#baseUrl = new URL('/api/v1/', url.origin);
    this.#fetch = fetchImpl;
    this.#timeoutMs = timeoutMs;
  }

  get authenticated() {
    return this.#token !== null;
  }

  /**
   * Emite un token de corta vigencia. El cliente nunca escribe secretos en disco
   * ni reintenta automáticamente un POST de autenticación.
   */
  async login({ email, password, deviceName = 'siget-integration' } = {}) {
    if (!email || !password || !deviceName) throw new TypeError('email, password y deviceName son obligatorios.');
    this.#token = null;
    const result = await this.request('POST', 'auth/token', {
      authorized: false,
      body: { email, password, device_name: deviceName },
    });
    if (result?.token_type !== 'Bearer' || typeof result.access_token !== 'string' || !result.access_token) {
      throw new SigetApiError('SIGET no devolvió un token de acceso válido.');
    }
    this.#token = result.access_token;
    return { user: result.user, expires_at: result.expires_at };
  }

  async me() {
    return this.get('me');
  }

  async logout() {
    try {
      if (this.#token) return await this.delete('auth/token');
      return null;
    } finally {
      this.#token = null;
    }
  }

  clearToken() {
    this.#token = null;
  }

  get(path, options) {
    return this.request('GET', path, options);
  }

  post(path, body, options = {}) {
    return this.request('POST', path, { ...options, body });
  }

  put(path, body, options = {}) {
    return this.request('PUT', path, { ...options, body });
  }

  patch(path, body, options = {}) {
    return this.request('PATCH', path, { ...options, body });
  }

  delete(path, options) {
    return this.request('DELETE', path, options);
  }

  /** Consultas habituales: el alcance de datos lo determina el backend. */
  agencies() {
    return this.get('agencies');
  }

  loads(filters = {}) {
    return this.get('loads', { params: filters });
  }

  load(id) {
    return this.get('loads/' + this.#id(id));
  }

  calendar({ start, end, contracting_agency_id } = {}) {
    return this.get('calendar/events', { params: { start, end, contracting_agency_id } });
  }

  /** Multipart; no establece Content-Type, para que fetch añada boundary. */
  uploadEvidence({ deliverableId, file, title } = {}) {
    if (!deliverableId || !file) throw new TypeError('deliverableId y file son obligatorios.');
    const form = new FormData();
    form.set('deliverable_id', String(this.#id(deliverableId)));
    form.set('file', file);
    if (title !== undefined) form.set('title', String(title));
    return this.post('evidences', form);
  }

  /** Descargar PDF/CSV/XLSX o evidencia sin convertir bytes a texto. */
  download(path, { params, signal } = {}) {
    return this.request('GET', path, { params, signal, responseType: 'arrayBuffer' });
  }

  #id(value) {
    const id = String(value);
    if (!/^[1-9][0-9]*$/.test(id)) throw new TypeError('El identificador debe ser un entero positivo.');
    return id;
  }

  async request(method, path, { params = {}, body, authorized = true, signal, responseType = 'json' } = {}) {
    const verb = String(method).toUpperCase();
    if (!['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].includes(verb)) throw new TypeError('Método HTTP no admitido.');
    if (typeof path !== 'string' || !/^[a-zA-Z0-9_/-]+$/.test(path) || path.startsWith('/') || path.includes('//')) {
      throw new TypeError('Se requiere una ruta relativa válida, sin query ni origen externo.');
    }
    if (!['json', 'arrayBuffer'].includes(responseType)) throw new TypeError('responseType no admitido.');
    if (authorized && !this.#token) throw new SigetApiError('No hay token de SIGET. Inicie sesión primero.', { status: 401 });

    const url = new URL(path, this.#baseUrl);
    if (!url.pathname.startsWith('/api/v1/')) throw new TypeError('Ruta fuera del prefijo API.');
    for (const [key, value] of Object.entries(params ?? {})) {
      if (value !== null && value !== undefined && value !== '') url.searchParams.set(key, String(value));
    }

    const headers = new Headers({ Accept: responseType === 'arrayBuffer' ? '*/*' : 'application/json' });
    if (authorized) headers.set('Authorization', 'Bearer ' + this.#token);
    let payload;
    if (body !== undefined && body !== null) {
      if (body instanceof FormData) payload = body;
      else {
        headers.set('Content-Type', 'application/json');
        payload = JSON.stringify(body);
      }
    }
    const timeout = AbortSignal.timeout(this.#timeoutMs);
    const combinedSignal = signal ? AbortSignal.any([signal, timeout]) : timeout;
    const response = await this.#fetch(url, { method: verb, headers, body: payload, signal: combinedSignal, credentials: 'omit', redirect: 'error' });
    const contentType = response.headers.get('content-type') ?? '';
    const result = response.status === 204 ? null
      : contentType.includes('application/json') || contentType.includes('+json')
        ? await response.json()
        : responseType === 'arrayBuffer' && response.ok ? await response.arrayBuffer() : await response.text();

    if (!response.ok) {
      if (authorized && response.status === 401) this.#token = null;
      throw new SigetApiError(
        typeof result?.message === 'string' ? result.message : 'Error al comunicarse con SIGET.',
        {
          status: response.status,
          errors: result && typeof result === 'object' ? result.errors ?? null : null,
          retryAfter: response.headers.get('Retry-After'),
          requestId: response.headers.get('X-Request-ID'),
        },
      );
    }
    return result;
  }
}
