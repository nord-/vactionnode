import { describe, it, expect, vi } from 'vitest';
import { createStore } from '../src/composables/useStore.js';

function fakeDataStore(overrides = {}) {
  return {
    bootstrap: vi.fn().mockResolvedValue({
      me: { id: 'p1', isAdmin: false },
      groups: [{ id: 'g1', name: 'A' }],
      persons: [{ id: 'p1', name: 'Anna', groupId: 'g1' }],
      vacations: [],
    }),
    ...overrides,
  };
}

describe('useStore', () => {
  it('refresh fyller state från bootstrap', async () => {
    const store = createStore(fakeDataStore());

    await store.refresh('2026-06-01', '2026-06-30');

    expect(store.groups.value).toHaveLength(1);
    expect(store.persons.value).toHaveLength(1);
    expect(store.me.value.id).toBe('p1');
    expect(store.error.value).toBe(null);
  });

  it('sätter error vid läsfel', async () => {
    const ds = fakeDataStore({
      bootstrap: vi.fn().mockRejectedValue(new Error('nätverk')),
    });
    const store = createStore(ds);

    await store.refresh('2026-06-01', '2026-06-30');

    expect(store.error.value).toBeTruthy();
  });

  it('lägger till den skapade raden lokalt utan att ladda om allt', async () => {
  const dataStore = fakeDataStore({
    addVacation: vi.fn().mockResolvedValue(
      { id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' }
    ),
  });
  const store = createStore(dataStore);
  await store.refresh('2026-06-01', '2026-06-30');

  await store.addVacation('p1', '2026-06-16', '2026-06-20');

  expect(store.vacations.value).toHaveLength(1);
  expect(dataStore.bootstrap).toHaveBeenCalledTimes(1);
});

it('tar bort raden lokalt vid radering', async () => {
  const dataStore = fakeDataStore({
    bootstrap: vi.fn().mockResolvedValue({
      me: { id: 'p1' }, groups: [], persons: [],
      vacations: [{ id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' }],
    }),
    removeVacation: vi.fn().mockResolvedValue({ ok: true }),
  });
  const store = createStore(dataStore);
  await store.refresh('2026-06-01', '2026-06-30');

  await store.removeVacation('v1');

  expect(store.vacations.value).toHaveLength(0);
  expect(dataStore.bootstrap).toHaveBeenCalledTimes(1);
  });
});
