import { ref } from 'vue';

export function createStore(dataStore) {
  const groups = ref([]);
  const persons = ref([]);
  const vacations = ref([]);
  const me = ref(null);
  const loading = ref(false);
  const error = ref(null);
  let range = { from: null, to: null };

  async function refresh(from = range.from, to = range.to) {
    range = { from, to };
    loading.value = true;
    error.value = null;
    try {
      const data = await dataStore.bootstrap(from, to);
      me.value = data.me;
      groups.value = data.groups;
      persons.value = data.persons;
      vacations.value = data.vacations;
    } catch (e) {
      error.value = e.message || 'Ett fel uppstod vid laddning.';
    } finally {
      loading.value = false;
    }
  }

  // Skrivningar uppdaterar listorna lokalt från serverns svar. Full omladdning
  // sker bara vid appstart och när datumintervallet ändras — annars laddades
  // hela datasetet om varje gång ett datum flyttades.
  function mutate(fn) {
    return async (...args) => {
      try {
        return await fn(...args);
      } catch (e) {
        error.value = e.message || 'Ett fel uppstod vid sparande.';
        throw e;
      }
    };
  }

  function replace(list, row) {
    const i = list.value.findIndex((x) => x.id === row.id);
    if (i === -1) list.value = [...list.value, row];
    else list.value = list.value.map((x) => (x.id === row.id ? { ...x, ...row } : x));
  }

  return {
    groups, persons, vacations, me, loading, error, refresh,

    addGroup: mutate(async (name) => replace(groups, await dataStore.addGroup(name))),
    renameGroup: mutate(async (id, name) => replace(groups, await dataStore.renameGroup(id, name))),
    removeGroup: mutate(async (id) => {
      await dataStore.removeGroup(id);
      groups.value = groups.value.filter((g) => g.id !== id);
      persons.value = persons.value.map((p) => (p.groupId === id ? { ...p, groupId: null } : p));
    }),

    addPerson: mutate(async (payload) => replace(persons, await dataStore.addPerson(payload))),
    updatePerson: mutate(async (id, patch) =>
      replace(persons, await dataStore.updatePerson(id, patch))),
    setPersonGroup: mutate(async (id, groupId) =>
      replace(persons, await dataStore.setPersonGroup(id, groupId))),
    removePerson: mutate(async (id) => {
      await dataStore.removePerson(id);
      persons.value = persons.value.filter((p) => p.id !== id);
      vacations.value = vacations.value.filter((v) => v.personId !== id);
    }),

    addVacation: mutate(async (personId, start, end) =>
      replace(vacations, await dataStore.addVacation(personId, start, end))),
    updateVacation: mutate(async (id, start, end) =>
      replace(vacations, await dataStore.updateVacation(id, start, end))),
    removeVacation: mutate(async (id) => {
      await dataStore.removeVacation(id);
      vacations.value = vacations.value.filter((v) => v.id !== id);
    }),
  };
}
