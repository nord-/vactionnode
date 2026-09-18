import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import App from '../src/App.vue';

function jsonResponse(body, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  };
}

describe('App – sparfel på huvudflödet', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
  });

  it('visar felet i modalen och håller den öppen när sparande av semester misslyckas', async () => {
    const me = { id: 'p1', name: 'Anna', email: 'anna@example.com', groupId: null, isAdmin: false };

    vi.stubGlobal('fetch', vi.fn(async (url, options = {}) => {
      const method = options.method ?? 'GET';
      if (url.includes('/api/session') && method === 'GET') return jsonResponse(me);
      if (url.includes('/api/bootstrap')) {
        return jsonResponse({ me, groups: [], persons: [me], vacations: [] });
      }
      if (url.includes('/api/vacations') && method === 'POST') {
        return jsonResponse(
          { error: { code: 'validation_failed', message: 'Ogiltigt datumintervall.' } },
          422,
        );
      }
      return jsonResponse({}, 200);
    }));

    const wrapper = mount(App);
    await flushPromises();

    await wrapper.find('[data-testid="add-vacation"]').trigger('click');
    await wrapper.find('[data-testid="start"]').setValue('2026-06-16');
    await wrapper.find('[data-testid="end"]').setValue('2026-06-20');
    await wrapper.find('[data-testid="save"]').trigger('click');
    await flushPromises();

    expect(wrapper.find('[data-testid="vacation-error"]').text()).toContain('Ogiltigt datumintervall.');
    // Modalen ligger som ett eget <dialog>-lager ovanpå resten av sidan —
    // felet måste synas där, och modalen får inte stängas vid ett fel.
    expect(wrapper.find('dialog').attributes('open')).toBeDefined();
  });
});
