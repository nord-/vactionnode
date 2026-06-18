export class GithubApiError extends Error {
  constructor(message, status) {
    super(message);
    this.name = 'GithubApiError';
    this.status = status;
  }
}

function encodeContent(str) {
  return btoa(unescape(encodeURIComponent(str)));
}
function decodeContent(b64) {
  return decodeURIComponent(escape(atob(b64)));
}

export function createGithubClient({ owner, repo, branch, token, fetchImpl = fetch }) {
  const baseUrl = `https://api.github.com/repos/${owner}/${repo}/contents`;
  const headers = {
    Accept: 'application/vnd.github+json',
    Authorization: `Bearer ${token}`,
  };

  async function readFile(path) {
    const res = await fetchImpl(`${baseUrl}/${path}?ref=${branch}`, { headers });
    if (res.status === 404) return { data: null, sha: null };
    if (!res.ok) throw new GithubApiError(`Läsfel ${res.status}`, res.status);
    const body = await res.json();
    try {
      return { data: JSON.parse(decodeContent(body.content)), sha: body.sha };
    } catch {
      return { data: null, sha: null }; // korrupt JSON → tomt dataset
    }
  }

  async function writeFile(path, mutate, { emptyValue = {}, maxRetries = 3 } = {}) {
    for (let attempt = 0; attempt < maxRetries; attempt++) {
      const { data, sha } = await readFile(path);
      const current = data ?? structuredClone(emptyValue);
      const next = mutate(current);
      const res = await fetchImpl(`${baseUrl}/${path}`, {
        method: 'PUT',
        headers,
        body: JSON.stringify({
          message: `Uppdatera ${path}`,
          content: encodeContent(JSON.stringify(next, null, 2)),
          branch,
          ...(sha ? { sha } : {}),
        }),
      });
      if (res.ok) return next;
      if (res.status === 409) continue; // konflikt → läs om och gör om mutate
      throw new GithubApiError(`Skrivfel ${res.status}`, res.status);
    }
    throw new GithubApiError('Skrivkonflikt kvarstod efter flera försök', 409);
  }

  return { readFile, writeFile };
}
