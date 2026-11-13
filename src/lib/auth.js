import { ApiError } from './apiClient.js';

export function login(api, email, password) {
  return api.post('/session', { email, password });
}

export function logout(api) {
  return api.del('/session');
}

// Sessionen är identiteten. Ingen flagga i localStorage, inget personId.
export async function currentUser(api) {
  try {
    return await api.get('/session');
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) return null;
    throw e;
  }
}

// Ett fel här får inte stoppa lösenordsinloggningen — knappen döljs bara.
export async function loginOptions(api) {
  try {
    const options = await api.get('/session/options');
    return { microsoft: options?.microsoft === true };
  } catch {
    return { microsoft: false };
  }
}

// Koderna kommer från SsoHandler via /?sso_error=. Okända koder får den
// allmänna texten — URL:en är användarstyrd och visas aldrig som den är.
const SSO_ERRORS = {
  denied: 'Inloggningen med Microsoft avbröts.',
  state_mismatch: 'Inloggningen med Microsoft tog för lång tid eller startades om. Försök igen.',
  account_conflict:
    'E-postadressen är redan kopplad till ett annat Microsoft-konto. Kontakta en administratör.',
  no_email: 'Microsoft-kontot saknar e-postadress. Kontakta en administratör.',
  guest: 'Gästkonton kan inte logga in med Microsoft. Kontakta en administratör.',
  acct_missing: 'Microsoft-inloggningen är inte färdigkonfigurerad. Kontakta en administratör.',
};

// En misslyckad Microsoft-inloggning landar på /?sso_error=<kod>. Ger koden
// (null om den saknas) och adressen utan parametern, med övriga parametrar
// och hashen (sidvalet) orörda, så att en omladdning inte visar felet igen.
export function splitSsoError(href) {
  const url = new URL(href);
  const code = url.searchParams.get('sso_error');
  url.searchParams.delete('sso_error');
  return { code, url: `${url.pathname}${url.search}${url.hash}` };
}

export function ssoErrorMessage(code) {
  return Object.hasOwn(SSO_ERRORS, code)
    ? SSO_ERRORS[code]
    : 'Inloggningen med Microsoft misslyckades. Försök igen.';
}

export function changePassword(api, currentPassword, newPassword) {
  return api.put('/session/password', { currentPassword, newPassword });
}
