const EMPTY_REGISTRY = { groups: [], persons: [] };
const EMPTY_VACATIONS = { vacations: [] };

export function createDataStore({ client, registryPath, vacationsPath }) {
  async function loadRegistry() {
    const { data } = await client.readFile(registryPath);
    return data ?? structuredClone(EMPTY_REGISTRY);
  }

  async function loadVacations() {
    const { data } = await client.readFile(vacationsPath);
    return data ?? structuredClone(EMPTY_VACATIONS);
  }

  function writeRegistry(mutate) {
    return client.writeFile(registryPath, mutate, { emptyValue: EMPTY_REGISTRY });
  }
  function writeVacations(mutate) {
    return client.writeFile(vacationsPath, mutate, { emptyValue: EMPTY_VACATIONS });
  }

  return {
    loadRegistry,
    loadVacations,

    addGroup(name) {
      return writeRegistry((r) => ({
        ...r,
        groups: [...r.groups, { id: crypto.randomUUID(), name }],
      }));
    },

    renameGroup(id, name) {
      return writeRegistry((r) => ({
        ...r,
        groups: r.groups.map((g) => (g.id === id ? { ...g, name } : g)),
      }));
    },

    removeGroup(id) {
      return writeRegistry((r) => ({
        groups: r.groups.filter((g) => g.id !== id),
        persons: r.persons.map((p) => (p.groupId === id ? { ...p, groupId: null } : p)),
      }));
    },

    // Returnerar den skapade personen (stabilt id även om writeFile gör om mutate vid 409).
    async addPerson(name, groupId = null) {
      const person = { id: crypto.randomUUID(), name, groupId };
      await writeRegistry((r) => ({ ...r, persons: [...r.persons, person] }));
      return person;
    },

    setPersonGroup(id, groupId) {
      return writeRegistry((r) => ({
        ...r,
        persons: r.persons.map((p) => (p.id === id ? { ...p, groupId } : p)),
      }));
    },

    async removePerson(id) {
      await writeRegistry((r) => ({ ...r, persons: r.persons.filter((p) => p.id !== id) }));
      return writeVacations((v) => ({ vacations: v.vacations.filter((x) => x.personId !== id) }));
    },

    addVacation(personId, start, end) {
      return writeVacations((v) => ({
        vacations: [...v.vacations, { id: crypto.randomUUID(), personId, start, end }],
      }));
    },

    updateVacation(id, start, end) {
      return writeVacations((v) => ({
        vacations: v.vacations.map((x) => (x.id === id ? { ...x, start, end } : x)),
      }));
    },

    removeVacation(id) {
      return writeVacations((v) => ({ vacations: v.vacations.filter((x) => x.id !== id) }));
    },
  };
}
