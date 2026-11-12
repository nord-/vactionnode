export function createDataStore({ api }) {
  return {
    bootstrap(from, to) {
      return api.get(`/bootstrap?from=${from}&to=${to}`);
    },

    addGroup(name) {
      return api.post('/groups', { name });
    },
    renameGroup(id, name) {
      return api.put(`/groups/${id}`, { name });
    },
    removeGroup(id) {
      return api.del(`/groups/${id}`);
    },

    addPerson({ name, email, password, groupId = null, isAdmin = false }) {
      return api.post('/persons', { name, email, password, groupId, isAdmin });
    },
    // Skickar bara de fält som faktiskt ändras. Servern lämnar övriga orörda,
    // så ett gruppbyte skriver inte över namn eller e-post.
    updatePerson(id, patch) {
      return api.put(`/persons/${id}`, patch);
    },
    setPersonGroup(id, groupId) {
      return api.put(`/persons/${id}`, { groupId });
    },
    removePerson(id) {
      return api.del(`/persons/${id}`);
    },

    addVacation(personId, start, end) {
      return api.post('/vacations', { personId, start, end });
    },
    updateVacation(id, start, end) {
      return api.put(`/vacations/${id}`, { start, end });
    },
    removeVacation(id) {
      return api.del(`/vacations/${id}`);
    },
  };
}
