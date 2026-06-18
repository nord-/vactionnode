const KEY = 'authenticated';

export function isAuthenticated(storage = localStorage) {
  return storage.getItem(KEY) === 'true';
}

export function login(password, expected, storage = localStorage) {
  if (password === expected) {
    storage.setItem(KEY, 'true');
    return true;
  }
  return false;
}

export function logout(storage = localStorage) {
  storage.removeItem(KEY);
}
