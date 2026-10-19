import { SigetClient, SigetApiError } from './siget-client.mjs';

const { SIGET_API_URL: baseUrl, SIGET_API_EMAIL: email, SIGET_API_PASSWORD: password } = process.env;
if (!baseUrl || !email || !password) {
  console.error('Se requieren SIGET_API_URL, SIGET_API_EMAIL y SIGET_API_PASSWORD (variables de entorno).');
  process.exitCode = 2;
} else {
  const client = new SigetClient({ baseUrl });
  try {
    const { user } = await client.login({ email, password, deviceName: 'siget-smoke' });
    if (!user?.id) throw new Error('La respuesta de autenticación no incluyó identidad.');
    const me = await client.me();
    if (me?.data?.id !== user.id) throw new Error('La identidad de /me no coincide con la del token.');
    const agencies = await client.agencies();
    if (!Array.isArray(agencies?.data)) throw new Error('/agencies no devolvió una lista.');
    console.log('SIGET API v1: autenticación, /me y /agencies correctos.');
    console.log('Identidad validada; dependencias visibles:', agencies.data.length);
  } catch (error) {
    if (error instanceof SigetApiError) {
      console.error('API SIGET:', error.status, error.message);
    } else {
      console.error('Verificación incompleta:', error.message);
    }
    process.exitCode = 1;
  } finally {
    try {
      await client.logout();
    } catch {
      console.error('No se pudo verificar la revocación del token; revísela en el servidor.');
      process.exitCode = 1;
    }
  }
}
