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

export function changePassword(api, currentPassword, newPassword) {
  return api.put('/session/password', { currentPassword, newPassword });
}
