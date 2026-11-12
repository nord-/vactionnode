import { describe, it, expect, vi } from 'vitest';
import { createApiClient, ApiError } from '../src/lib/apiClient.js';

function response(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  };
}

describe('apiClient', () => {
  it('skickar JSON-header på skrivningar', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(response(200, { ok: true }));
    const api = createApiClient({ fetchImpl });

    await api.post('/vacations', { start: '2026-06-16' });

    const [url, init] = fetchImpl.mock.calls[0];
    expect(url).toBe('/api/vacations');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(init.credentials).toBe('same-origin');
  });

  it('anropar onUnauthorized vid 401', async () => {
    const onUnauthorized = vi.fn();
    const fetchImpl = vi.fn().mockResolvedValue(
      response(401, { error: { code: 'unauthenticated', message: 'Du måste logga in.' } })
    );
    const api = createApiClient({ fetchImpl, onUnauthorized });

    await expect(api.get('/bootstrap')).rejects.toBeInstanceOf(ApiError);
    expect(onUnauthorized).toHaveBeenCalledOnce();
  });

  it('bär med serverns felmeddelande vid 403 och 422', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(
      response(403, { error: { code: 'forbidden', message: 'Du får bara ändra din egen semester.' } })
    );
    const api = createApiClient({ fetchImpl });

    await expect(api.put('/vacations/1', {})).rejects.toMatchObject({
      status: 403,
      code: 'forbidden',
      message: 'Du får bara ändra din egen semester.',
    });
  });

  it('ger ett begripligt fel när nätverket ligger nere', async () => {
    const fetchImpl = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
    const api = createApiClient({ fetchImpl });

    await expect(api.get('/bootstrap')).rejects.toMatchObject({
      status: 0,
      code: 'network_error',
    });
  });
});
