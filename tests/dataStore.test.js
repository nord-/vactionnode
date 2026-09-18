import { describe, it, expect, vi } from 'vitest';
import { createDataStore } from '../src/lib/dataStore.js';

function fakeApi(overrides = {}) {
  return {
    get: vi.fn().mockResolvedValue({}),
    post: vi.fn().mockResolvedValue({}),
    put: vi.fn().mockResolvedValue({}),
    del: vi.fn().mockResolvedValue({ ok: true }),
    ...overrides,
  };
}

describe('dataStore', () => {
  it('hämtar startdata med datumintervall', async () => {
    const api = fakeApi({
      get: vi.fn().mockResolvedValue({ me: { id: 'u1' }, groups: [], persons: [], vacations: [] }),
    });
    const store = createDataStore({ api });

    const data = await store.bootstrap('2026-06-01', '2026-06-30');

    expect(api.get).toHaveBeenCalledWith('/bootstrap?from=2026-06-01&to=2026-06-30');
    expect(data.me.id).toBe('u1');
  });

  it('skickar bara groupId vid gruppbyte', async () => {
    const api = fakeApi();
    const store = createDataStore({ api });

    await store.setPersonGroup('p1', 't1');

    expect(api.put).toHaveBeenCalledWith('/persons/p1', { groupId: 't1' });
  });

  it('skickar groupId null när personen ska bli ogrupperad', async () => {
    const api = fakeApi();
    const store = createDataStore({ api });

    await store.setPersonGroup('p1', null);

    expect(api.put).toHaveBeenCalledWith('/persons/p1', { groupId: null });
  });

  it('skickar bara lösenord vid lösenordsbyte i adminpanelen', async () => {
    const api = fakeApi();
    const store = createDataStore({ api });

    await store.updatePerson('p1', { password: 'nyttlosen123' });

    expect(api.put).toHaveBeenCalledWith('/persons/p1', { password: 'nyttlosen123' });
  });

  it('returnerar den skapade semesterraden i stället för hela datasetet', async () => {
    const created = { id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' };
    const api = fakeApi({ post: vi.fn().mockResolvedValue(created) });
    const store = createDataStore({ api });

    const row = await store.addVacation('p1', '2026-06-16', '2026-06-20');

    expect(api.post).toHaveBeenCalledWith('/vacations',
      { personId: 'p1', start: '2026-06-16', end: '2026-06-20' });
    expect(row).toEqual(created);
  });
});
