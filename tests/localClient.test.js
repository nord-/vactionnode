import { describe, it, expect, beforeEach } from 'vitest';
import { createLocalClient } from '../src/lib/localClient.js';

// Enkel in-memory storage som efterliknar localStorage-API:t.
function fakeStorage() {
  const map = new Map();
  return {
    getItem: (k) => (map.has(k) ? map.get(k) : null),
    setItem: (k, v) => map.set(k, String(v)),
    removeItem: (k) => map.delete(k),
    clear: () => map.clear(),
  };
}

describe('createLocalClient', () => {
  let storage;
  beforeEach(() => {
    storage = fakeStorage();
  });

  it('returnerar tomt för en fil som inte finns', async () => {
    const client = createLocalClient({ storage });
    expect(await client.readFile('data/registry.json')).toEqual({ data: null, sha: null });
  });

  it('skriver och läser tillbaka samma data', async () => {
    const client = createLocalClient({ storage });
    await client.writeFile('p', (d) => ({ items: [...d.items, 'a'] }), { emptyValue: { items: [] } });
    expect(await client.readFile('p')).toEqual({ data: { items: ['a'] }, sha: 'local' });
  });

  it('kör mutate på befintlig data', async () => {
    const client = createLocalClient({ storage });
    await client.writeFile('p', () => ({ items: ['a'] }));
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'b'] }));
    expect(result).toEqual({ items: ['a', 'b'] });
    expect(await client.readFile('p')).toEqual({ data: { items: ['a', 'b'] }, sha: 'local' });
  });

  it('använder emptyValue när filen saknas', async () => {
    const client = createLocalClient({ storage });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'a'] }), {
      emptyValue: { items: [] },
    });
    expect(result).toEqual({ items: ['a'] });
  });

  it('returnerar tomt vid korrupt JSON', async () => {
    const client = createLocalClient({ storage, prefix: 'vac:' });
    storage.setItem('vac:p', '{ not valid json');
    expect(await client.readFile('p')).toEqual({ data: null, sha: null });
  });

  it('separerar nycklar med prefix så två klienter inte krockar', async () => {
    const a = createLocalClient({ storage, prefix: 'a:' });
    const b = createLocalClient({ storage, prefix: 'b:' });
    await a.writeFile('p', () => ({ v: 1 }));
    await b.writeFile('p', () => ({ v: 2 }));
    expect((await a.readFile('p')).data).toEqual({ v: 1 });
    expect((await b.readFile('p')).data).toEqual({ v: 2 });
  });
});
