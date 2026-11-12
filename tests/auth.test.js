import { describe, it, expect, vi } from 'vitest';
import { login, logout, currentUser } from '../src/lib/auth.js';
import { ApiError } from '../src/lib/apiClient.js';

describe('auth', () => {
  it('loggar in mot /session och returnerar användaren', async () => {
    const api = { post: vi.fn().mockResolvedValue({ id: 'u1', isAdmin: false }) };

    const user = await login(api, 'anna@example.com', 'hemligt123');

    expect(api.post).toHaveBeenCalledWith('/session',
      { email: 'anna@example.com', password: 'hemligt123' });
    expect(user.id).toBe('u1');
  });

  it('returnerar null från currentUser när sessionen saknas', async () => {
    const api = { get: vi.fn().mockRejectedValue(new ApiError('nej', 401, 'unauthenticated')) };

    expect(await currentUser(api)).toBeNull();
  });

  it('låter andra fel bubbla upp från currentUser', async () => {
    const api = { get: vi.fn().mockRejectedValue(new ApiError('trasig', 500, 'server_error')) };

    await expect(currentUser(api)).rejects.toMatchObject({ status: 500 });
  });

  it('skriver inte längre någon flagga i localStorage', async () => {
    const api = { post: vi.fn().mockResolvedValue({ id: 'u1' }) };
    localStorage.clear();

    await login(api, 'anna@example.com', 'hemligt123');

    expect(localStorage.getItem('authenticated')).toBeNull();
  });

  it('loggar ut mot /session', async () => {
    const api = { del: vi.fn().mockResolvedValue({ ok: true }) };

    await logout(api);

    expect(api.del).toHaveBeenCalledWith('/session');
  });
});
