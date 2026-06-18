import { describe, it, expect, beforeEach } from 'vitest';
import { getPersonId, setPersonId, resolveIdentity } from '../src/lib/identity.js';

describe('identity', () => {
  beforeEach(() => localStorage.clear());

  it('saknar personId från start', () => {
    expect(getPersonId()).toBe(null);
  });
  it('setPersonId lagrar och getPersonId läser', () => {
    setPersonId('p1');
    expect(getPersonId()).toBe('p1');
  });
  it('resolveIdentity returnerar id som finns i registret', () => {
    setPersonId('p1');
    expect(resolveIdentity([{ id: 'p1', name: 'Anna' }])).toBe('p1');
  });
  it('resolveIdentity rensar och returnerar null för okänt id', () => {
    setPersonId('borttagen');
    expect(resolveIdentity([{ id: 'p1', name: 'Anna' }])).toBe(null);
    expect(getPersonId()).toBe(null);
  });
});
