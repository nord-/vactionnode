export class ApiError extends Error {
  constructor(message, status, code) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

export function createApiClient({ baseUrl = '/api', fetchImpl = fetch, onUnauthorized } = {}) {
  async function send(method, path, body) {
    let res;
    try {
      res = await fetchImpl(`${baseUrl}${path}`, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
      });
    } catch {
      throw new ApiError('Kunde inte nå servern. Kontrollera din anslutning.', 0, 'network_error');
    }

    const payload = await res.json().catch(() => ({}));

    if (res.ok) return payload;

    if (res.status === 401 && onUnauthorized) onUnauthorized();

    const error = payload.error ?? {};
    throw new ApiError(error.message ?? 'Ett fel uppstod.', res.status, error.code ?? 'unknown');
  }

  return {
    get: (path) => send('GET', path),
    post: (path, body) => send('POST', path, body ?? {}),
    put: (path, body) => send('PUT', path, body ?? {}),
    del: (path) => send('DELETE', path),
  };
}
