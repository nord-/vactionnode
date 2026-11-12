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

  // En semester i innevarande månad, så den ligger inom standardintervallet.
  function firstOfThisMonth() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
  }

  async function mountWithColleagueVacation(me) {
    const colleague = { id: 'p2', name: 'Bo', email: 'bo@example.com', groupId: null, isAdmin: false };
    const day = firstOfThisMonth();
    const vacation = { id: 'v1', personId: 'p2', start: day, end: day };

    vi.stubGlobal('fetch', vi.fn(async (url, options = {}) => {
      const method = options.method ?? 'GET';
      if (url.includes('/api/session') && method === 'GET') return jsonResponse(me);
      if (url.includes('/api/bootstrap')) {
        return jsonResponse({ me, groups: [], persons: [me, colleague], vacations: [vacation] });
      }
      return jsonResponse({}, 200);
    }));

    const wrapper = mount(App);
    await flushPromises();
    await wrapper.find('td[data-vacation="true"]').trigger('click');
    await flushPromises();

    return wrapper;
  }

  it('visar vems semester en administratör redigerar, inte administratörens eget namn', async () => {
    const admin = { id: 'p1', name: 'Anna', email: 'anna@example.com', groupId: null, isAdmin: true };

    const wrapper = await mountWithColleagueVacation(admin);

    expect(wrapper.find('dialog').attributes('open')).toBeDefined();
    const heading = wrapper.find('dialog h3').text();
    expect(heading).toContain('Bo');
    expect(heading).not.toContain('Anna');
  });

  it('öppnar inte redigeringsdialogen för en annans semester när man inte är administratör', async () => {
    const user = { id: 'p1', name: 'Anna', email: 'anna@example.com', groupId: null, isAdmin: false };

    const wrapper = await mountWithColleagueVacation(user);

    expect(wrapper.find('dialog').attributes('open')).toBeUndefined();
  });
});

describe('App – meny och sidor', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
    window.location.hash = '';
  });

  async function mountAs(me) {
    vi.stubGlobal('fetch', vi.fn(async (url, options = {}) => {
      const method = options.method ?? 'GET';
      if (url.includes('/api/session') && method === 'GET') return jsonResponse(me);
      if (url.includes('/api/bootstrap')) {
        return jsonResponse({ me, groups: [], persons: [me], vacations: [] });
      }
      return jsonResponse({}, 200);
    }));

    const wrapper = mount(App);
    await flushPromises();
    return wrapper;
  }

  async function goTo(hash) {
    window.location.hash = hash;
    window.dispatchEvent(new HashChangeEvent('hashchange'));
    await flushPromises();
  }

  const user = { id: 'p1', name: 'Anna', email: 'anna@example.com', groupId: null, isAdmin: false };
  const admin = { ...user, isAdmin: true };

  it('visar Kalender, Byt lösenord och Logga ut men inte adminvalet för en vanlig användare', async () => {
    const wrapper = await mountAs(user);

    expect(wrapper.find('[data-testid="menu-calendar"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="menu-change-password"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="menu-logout"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="menu-admin"]').exists()).toBe(false);
  });

  it('visar adminvalet för en administratör', async () => {
    const wrapper = await mountAs(admin);

    expect(wrapper.find('[data-testid="menu-admin"]').attributes('href')).toBe('#/admin');
  });

  it('visar varken adminpanel eller lösenordsformulär på kalendersidan', async () => {
    const wrapper = await mountAs(admin);

    expect(wrapper.find('[data-testid="new-group"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="add-vacation"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="change-password-dialog"]').attributes('open')).toBeUndefined();
    expect(wrapper.find('[data-testid="menu-calendar"]').classes()).toContain('menu-active');
  });

  it('byter till adminsidan på #/admin och tillbaka till kalendern på #/', async () => {
    const wrapper = await mountAs(admin);

    await goTo('#/admin');
    expect(wrapper.find('[data-testid="new-group"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="add-vacation"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="menu-admin"]').classes()).toContain('menu-active');
    expect(wrapper.find('[data-testid="menu-calendar"]').classes()).not.toContain('menu-active');

    await goTo('#/');
    expect(wrapper.find('[data-testid="new-group"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="add-vacation"]').exists()).toBe(true);
  });

  it('visar kalendern för en vanlig användare som går till #/admin', async () => {
    window.location.hash = '#/admin';
    const wrapper = await mountAs(user);

    expect(wrapper.find('[data-testid="new-group"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="add-vacation"]').exists()).toBe(true);
  });

  it('öppnar lösenordsmodalen från menyn', async () => {
    const wrapper = await mountAs(user);

    await wrapper.find('[data-testid="menu-change-password"]').trigger('click');

    expect(wrapper.find('[data-testid="change-password-dialog"]').attributes('open')).toBeDefined();
  });

  it('loggar ut från menyn', async () => {
    const wrapper = await mountAs(user);

    await wrapper.find('[data-testid="menu-logout"]').trigger('click');
    await flushPromises();

    expect(wrapper.find('[data-testid="menu-logout"]').exists()).toBe(false);
    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
  });
});
