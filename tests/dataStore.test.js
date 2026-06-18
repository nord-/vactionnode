import { describe, it, expect, vi } from 'vitest';
import { createDataStore } from '../src/lib/dataStore.js';

// Fejkklient med data i minnet. writeFile kör mutate på aktuell fil.
function fakeClient(files) {
  return {
    readFile: vi.fn(async (path) => ({ data: files[path] ?? null, sha: 's' })),
    writeFile: vi.fn(async (path, mutate, { emptyValue } = {}) => {
      const current = files[path] ?? structuredClone(emptyValue ?? {});
      files[path] = mutate(current);
      return files[path];
    }),
  };
}

const paths = { registryPath: 'reg.json', vacationsPath: 'vac.json' };

describe('dataStore registry', () => {
  it('lägger till grupp och person', async () => {
    const files = {};
    const store = createDataStore({ client: fakeClient(files), ...paths });
    await store.addGroup('Team A');
    expect(files['reg.json'].groups[0].name).toBe('Team A');
    expect(files['reg.json'].groups[0].id).toBeTypeOf('string');
    const created = await store.addPerson('Anna');
    expect(files['reg.json'].persons[0]).toMatchObject({ name: 'Anna', groupId: null });
    expect(created).toMatchObject({ name: 'Anna', groupId: null });
    expect(created.id).toBe(files['reg.json'].persons[0].id);
  });

  it('removeGroup nullar groupId på medlemmar men raderar dem inte', async () => {
    const files = {
      'reg.json': {
        groups: [{ id: 'g1', name: 'Team A' }],
        persons: [{ id: 'p1', name: 'Anna', groupId: 'g1' }],
      },
    };
    const store = createDataStore({ client: fakeClient(files), ...paths });
    await store.removeGroup('g1');
    expect(files['reg.json'].groups).toEqual([]);
    expect(files['reg.json'].persons[0].groupId).toBe(null);
  });

  it('removePerson tar bort personen och dess semestrar', async () => {
    const files = {
      'reg.json': { groups: [], persons: [{ id: 'p1', name: 'Anna', groupId: null }] },
      'vac.json': {
        vacations: [
          { id: 'v1', personId: 'p1', start: '2025-06-01', end: '2025-06-05' },
          { id: 'v2', personId: 'p2', start: '2025-06-01', end: '2025-06-05' },
        ],
      },
    };
    const store = createDataStore({ client: fakeClient(files), ...paths });
    await store.removePerson('p1');
    expect(files['reg.json'].persons).toEqual([]);
    expect(files['vac.json'].vacations.map((v) => v.id)).toEqual(['v2']);
  });
});

describe('dataStore vacations', () => {
  it('lägger till, uppdaterar och tar bort semester', async () => {
    const files = {};
    const store = createDataStore({ client: fakeClient(files), ...paths });
    const added = await store.addVacation('p1', '2025-06-16', '2025-06-20');
    const id = added.vacations[0].id;
    expect(added.vacations[0]).toMatchObject({ personId: 'p1', start: '2025-06-16', end: '2025-06-20' });

    await store.updateVacation(id, '2025-06-17', '2025-06-21');
    expect(files['vac.json'].vacations[0]).toMatchObject({ start: '2025-06-17', end: '2025-06-21' });

    await store.removeVacation(id);
    expect(files['vac.json'].vacations).toEqual([]);
  });
});
