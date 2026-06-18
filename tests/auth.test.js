import { describe, it, expect, beforeEach } from 'vitest';
import { isAuthenticated, login, logout } from '../src/lib/auth.js';

describe('auth', () => {
  beforeEach(() => localStorage.clear());

  it('är inte autentiserad från start', () => {
    expect(isAuthenticated()).toBe(false);
  });
  it('login med rätt lösenord sätter flaggan', () => {
    expect(login('hemligt', 'hemligt')).toBe(true);
    expect(isAuthenticated()).toBe(true);
  });
  it('login med fel lösenord misslyckas', () => {
    expect(login('fel', 'hemligt')).toBe(false);
    expect(isAuthenticated()).toBe(false);
  });
  it('logout rensar flaggan', () => {
    login('hemligt', 'hemligt');
    logout();
    expect(isAuthenticated()).toBe(false);
  });
});
