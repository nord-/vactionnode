import { describe, it, expect, vi } from 'vitest';
import { createGithubClient } from '../src/lib/githubClient.js';

function b64(str) {
  return btoa(unescape(encodeURIComponent(str)));
}
function jsonResponse(obj, sha) {
  return {
    ok: true,
    status: 200,
    json: async () => ({ content: b64(JSON.stringify(obj)), sha }),
  };
}

const base = { owner: 'o', repo: 'r', branch: 'main', token: 't' };

describe('readFile', () => {
  it('parsar innehåll och returnerar sha', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ groups: [] }, 'sha1'));
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.readFile('data/registry.json');
    expect(result).toEqual({ data: { groups: [] }, sha: 'sha1' });
  });

  it('returnerar tomt vid 404', async () => {
    const fetchImpl = vi.fn().mockResolvedValue({ ok: false, status: 404 });
    const client = createGithubClient({ ...base, fetchImpl });
    expect(await client.readFile('x')).toEqual({ data: null, sha: null });
  });
});

describe('writeFile', () => {
  it('läser, muterar och PUTar med sha', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ items: [] }, 'sha1'))
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'a'] }));
    expect(result).toEqual({ items: ['a'] });
    const putCall = fetchImpl.mock.calls[1];
    const body = JSON.parse(putCall[1].body);
    expect(body.sha).toBe('sha1');
    expect(body.branch).toBe('main');
  });

  it('gör om mutate på färskt data vid 409', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ items: [] }, 'sha1'))
      .mockResolvedValueOnce({ ok: false, status: 409 })
      .mockResolvedValueOnce(jsonResponse({ items: ['other'] }, 'sha2'))
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'mine'] }));
    expect(result).toEqual({ items: ['other', 'mine'] });
  });

  it('använder emptyValue när filen saknas', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce({ ok: false, status: 404 })
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'a'] }), {
      emptyValue: { items: [] },
    });
    expect(result).toEqual({ items: ['a'] });
  });

  it('kastar med status vid 403', async () => {
    const fetchImpl = vi.fn().mockResolvedValue({ ok: false, status: 403 });
    const client = createGithubClient({ ...base, fetchImpl });
    await expect(client.writeFile('p', (d) => d, { emptyValue: {} })).rejects.toMatchObject({
      status: 403,
    });
  });
});
