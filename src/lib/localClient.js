// Lokalt dev-datalager som speglar githubClient-interfacet (readFile/writeFile)
// men lagrar i webbläsarens localStorage. Används när VITE_DATA_BACKEND=local
// så att appen kan köras helt utan GitHub API (ingen PAT/repo krävs).
// Ingen 409-retry behövs — det finns ingen samtidig skrivning lokalt.
export function createLocalClient({ storage = localStorage, prefix = 'vac:' } = {}) {
  function key(path) {
    return prefix + path;
  }

  async function readFile(path) {
    const raw = storage.getItem(key(path));
    if (raw == null) return { data: null, sha: null };
    try {
      return { data: JSON.parse(raw), sha: 'local' };
    } catch {
      return { data: null, sha: null }; // korrupt JSON → tomt dataset
    }
  }

  async function writeFile(path, mutate, { emptyValue = {} } = {}) {
    const { data } = await readFile(path);
    const current = data ?? structuredClone(emptyValue);
    const next = mutate(current);
    storage.setItem(key(path), JSON.stringify(next, null, 2));
    return next;
  }

  return { readFile, writeFile };
}
