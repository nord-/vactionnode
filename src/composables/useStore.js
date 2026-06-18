import { ref } from 'vue';

export function createStore(dataStore) {
  const groups = ref([]);
  const persons = ref([]);
  const vacations = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function refresh() {
    loading.value = true;
    error.value = null;
    try {
      const [reg, vac] = await Promise.all([
        dataStore.loadRegistry(),
        dataStore.loadVacations(),
      ]);
      groups.value = reg.groups;
      persons.value = reg.persons;
      vacations.value = vac.vacations;
    } catch (e) {
      error.value = e.message || 'Ett fel uppstod vid laddning.';
    } finally {
      loading.value = false;
    }
  }

  function action(fn) {
    return async (...args) => {
      try {
        const result = await fn(...args);
        await refresh();
        return result;
      } catch (e) {
        error.value = e.message || 'Ett fel uppstod vid sparande.';
        throw e;
      }
    };
  }

  return {
    groups,
    persons,
    vacations,
    loading,
    error,
    refresh,
    addGroup: action(dataStore.addGroup),
    renameGroup: action(dataStore.renameGroup),
    removeGroup: action(dataStore.removeGroup),
    addPerson: action(dataStore.addPerson),
    setPersonGroup: action(dataStore.setPersonGroup),
    removePerson: action(dataStore.removePerson),
    addVacation: action(dataStore.addVacation),
    updateVacation: action(dataStore.updateVacation),
    removeVacation: action(dataStore.removeVacation),
  };
}
