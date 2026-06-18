import { describe, it, expect, vi } from 'vitest';
import { createStore } from '../src/composables/useStore.js';

function fakeDataStore() {
  return {
    loadRegistry: vi.fn(async () => ({ groups: [{ id: 'g1', name: 'A' }], persons: [] })),
    loadVacations: vi.fn(async () => ({ vacations: [{ id: 'v1', personId: 'p1', start: '2025-06-01', end: '2025-06-02' }] })),
    addGroup: vi.fn(async () => {}),
  };
}

describe('useStore', () => {
  it('refresh fyller state', async () => {
    const store = createStore(fakeDataStore());
    await store.refresh();
    expect(store.groups.value).toHaveLength(1);
    expect(store.vacations.value).toHaveLength(1);
    expect(store.error.value).toBe(null);
  });

  it('action anropar dataStore, refreshar och returnerar resultatet', async () => {
    const ds = fakeDataStore();
    ds.addGroup = vi.fn(async () => ({ id: 'g99', name: 'B' }));
    const store = createStore(ds);
    const result = await store.addGroup('B');
    expect(ds.addGroup).toHaveBeenCalledWith('B');
    expect(ds.loadRegistry).toHaveBeenCalled();
    expect(result).toEqual({ id: 'g99', name: 'B' });
  });

  it('sätter error vid läsfel', async () => {
    const ds = fakeDataStore();
    ds.loadRegistry = vi.fn(async () => { throw new Error('nätverk'); });
    const store = createStore(ds);
    await store.refresh();
    expect(store.error.value).toBeTruthy();
  });
});
