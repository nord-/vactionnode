const KEY = 'personId';

export function getPersonId(storage = localStorage) {
  return storage.getItem(KEY);
}

export function setPersonId(id, storage = localStorage) {
  storage.setItem(KEY, id);
}

export function resolveIdentity(persons, storage = localStorage) {
  const id = getPersonId(storage);
  if (id && persons.some((p) => p.id === id)) return id;
  storage.removeItem(KEY);
  return null;
}
