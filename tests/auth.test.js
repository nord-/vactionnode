import { describe, it, expect, vi } from 'vitest';
import { login, logout, currentUser, loginOptions, ssoErrorMessage, splitSsoError } from '../src/lib/auth.js';
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

  it('läser om Microsoft-inloggningen är påslagen', async () => {
    const api = { get: vi.fn().mockResolvedValue({ microsoft: true }) };

    expect(await loginOptions(api)).toEqual({ microsoft: true });
    expect(api.get).toHaveBeenCalledWith('/session/options');
  });

  it('döljer Microsoft-knappen när servern inte svarar', async () => {
    const api = { get: vi.fn().mockRejectedValue(new ApiError('trasig', 500, 'server_error')) };

    expect(await loginOptions(api)).toEqual({ microsoft: false });
  });

  it('översätter en sso_error-kod till svensk text', () => {
    expect(ssoErrorMessage('account_conflict')).toMatch(/redan kopplad/);
    expect(ssoErrorMessage('denied')).toMatch(/avbröts/);
    expect(ssoErrorMessage('guest')).toMatch(/Gästkonton/);
    expect(ssoErrorMessage('acct_missing')).toMatch(/färdigkonfigurerad/);
  });

  it('plockar ut sso_error ur adressen och lämnar övriga parametrar och hashen', () => {
    expect(splitSsoError('https://x.se/?a=1&sso_error=guest#/admin'))
      .toEqual({ code: 'guest', url: '/?a=1#/admin' });
    expect(splitSsoError('https://x.se/?sso_error=denied'))
      .toEqual({ code: 'denied', url: '/' });
    expect(splitSsoError('https://x.se/#/admin').code).toBeNull();
  });

  it('ger ett allmänt meddelande för okända sso_error-koder', () => {
    expect(ssoErrorMessage('<script>')).toBe('Inloggningen med Microsoft misslyckades. Försök igen.');
  });

  it('loggar ut mot /session', async () => {
    const api = { del: vi.fn().mockResolvedValue({ ok: true }) };

    await logout(api);

    expect(api.del).toHaveBeenCalledWith('/session');
  });
});
