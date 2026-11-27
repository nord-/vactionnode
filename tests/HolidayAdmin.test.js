import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import HolidayAdmin from '../src/components/HolidayAdmin.vue';
import { typeDate } from './helpers/dateField.js';
import { formatDate } from '../src/lib/dateFormat.js';
import { userLocale } from '../src/lib/locale.js';

const ROWS = [
  { id: 1, date: '2027-01-01', name: 'Nyårsdagen' },
  { id: 2, date: '2027-01-06', name: 'Trettondedag jul' },
];

function makeStore(overrides = {}) {
  return {
    listHolidays: vi.fn().mockImplementation(async () => structuredClone(ROWS)),
    addHoliday: vi.fn().mockResolvedValue({}),
    renameHoliday: vi.fn().mockResolvedValue({}),
    removeHoliday: vi.fn().mockResolvedValue({ ok: true }),
    importHolidays: vi.fn().mockResolvedValue({ imported: 16, skipped: 0, untranslated: [] }),
    ...overrides,
  };
}

async function mountCard(overrides = {}, initialYear = 2027) {
  const dataStore = makeStore(overrides);
  const w = mount(HolidayAdmin, { props: { dataStore, initialYear } });
  await flushPromises();
  return { w, dataStore };
}

const rejection = { message: 'Helgdagen måste ha ett namn.' };

describe('HolidayAdmin', () => {
  it('hämtar och visar årets helgdagar med formaterat datum', async () => {
    const { w, dataStore } = await mountCard();
    expect(dataStore.listHolidays).toHaveBeenCalledWith(2027);
    expect(w.text()).toContain(formatDate('2027-01-01', userLocale()));
    expect(w.find('[data-testid="holiday-name-2"]').element.value).toBe('Trettondedag jul');
    expect(w.find('[data-testid="holiday-year"]').text()).toBe('2027');
  });

  it('bläddrar till nästa år', async () => {
    const { w, dataStore } = await mountCard();
    await w.find('[data-testid="holiday-next"]').trigger('click');
    await flushPromises();
    expect(dataStore.listHolidays).toHaveBeenLastCalledWith(2028);
  });

  it('stänger av pilarna vid 2000 och 2100', async () => {
    const hi = await mountCard({}, 2100);
    expect(hi.w.find('[data-testid="holiday-next"]').attributes('disabled')).toBeDefined();
    const lo = await mountCard({}, 2000);
    expect(lo.w.find('[data-testid="holiday-prev"]').attributes('disabled')).toBeDefined();
  });

  it('byter namn, emittar changed och laddar om listan', async () => {
    const { w, dataStore } = await mountCard();
    const input = w.find('[data-testid="holiday-name-1"]');
    input.element.value = 'Nytt';
    await input.trigger('change');
    await flushPromises();
    expect(dataStore.renameHoliday).toHaveBeenCalledWith(1, 'Nytt');
    expect(w.emitted('changed')).toHaveLength(1);
    expect(dataStore.listHolidays).toHaveBeenCalledTimes(2);
  });

  it('återställer fältet och visar felet när ett namnbyte avvisas', async () => {
    const { w } = await mountCard({ renameHoliday: vi.fn().mockRejectedValue(rejection) });
    const input = w.find('[data-testid="holiday-name-1"]');
    input.element.value = '';
    await input.trigger('change');
    await flushPromises();
    expect(input.element.value).toBe('Nyårsdagen');
    expect(w.find('[data-testid="holiday-error"]').text()).toBe(rejection.message);
    expect(w.emitted('changed')).toBeUndefined();
  });

  it('behåller det nya namnet och emittar changed när bara omladdningen misslyckas', async () => {
    const listHolidays = vi.fn()
      .mockResolvedValueOnce(structuredClone(ROWS))
      .mockRejectedValueOnce({ message: 'Kunde inte ladda om.' });
    const { w } = await mountCard({ listHolidays });
    const input = w.find('[data-testid="holiday-name-1"]');
    input.element.value = 'Nytt';
    await input.trigger('change');
    await flushPromises();
    expect(input.element.value).toBe('Nytt');
    expect(w.emitted('changed')).toHaveLength(1);
    expect(w.find('[data-testid="holiday-error"]').text()).toBe('Kunde inte ladda om.');
  });

  it('ignorerar ett sent svar för ett tidigare år', async () => {
    let resolveSlow;
    const listHolidays = vi.fn()
      .mockResolvedValueOnce(structuredClone(ROWS))
      .mockImplementationOnce(() => new Promise((r) => { resolveSlow = r; }))
      .mockResolvedValueOnce([{ id: 9, date: '2029-01-01', name: 'Nyår 2029' }]);
    const { w } = await mountCard({ listHolidays });
    await w.find('[data-testid="holiday-next"]').trigger('click');
    await w.find('[data-testid="holiday-next"]').trigger('click');
    await flushPromises();
    resolveSlow([{ id: 8, date: '2028-01-01', name: 'Nyår 2028' }]);
    await flushPromises();
    expect(w.find('[data-testid="holiday-year"]').text()).toBe('2029');
    expect(w.find('[data-testid="holiday-name-9"]').exists()).toBe(true);
    expect(w.find('[data-testid="holiday-name-8"]').exists()).toBe(false);
  });

  it('visar inte felet från ett sent avvisat svar för ett tidigare år', async () => {
    let rejectSlow;
    const listHolidays = vi.fn()
      .mockResolvedValueOnce(structuredClone(ROWS))
      .mockImplementationOnce(() => new Promise((_, reject) => { rejectSlow = reject; }))
      .mockResolvedValueOnce([{ id: 9, date: '2029-01-01', name: 'Nyår 2029' }]);
    const { w } = await mountCard({ listHolidays });
    await w.find('[data-testid="holiday-next"]').trigger('click');
    await w.find('[data-testid="holiday-next"]').trigger('click');
    await flushPromises();
    rejectSlow({ message: 'Tidsgränsen överskreds.' });
    await flushPromises();
    expect(w.find('[data-testid="holiday-year"]').text()).toBe('2029');
    expect(w.find('[data-testid="holiday-name-9"]').exists()).toBe(true);
    expect(w.find('[data-testid="holiday-error"]').exists()).toBe(false);
  });

  it('stänger av Importera och Lägg till medan en skrivning pågår', async () => {
    let finish;
    const importHolidays = vi.fn(() => new Promise((resolve) => { finish = resolve; }));
    const { w } = await mountCard({ importHolidays });
    await w.find('[data-testid="new-holiday-name"]').setValue('Dag');
    await typeDate(w, 'new-holiday-date', '2027-05-05');
    const importBtn = w.find('[data-testid="import-holidays"]');
    const addBtn = w.find('[data-testid="add-holiday"]');
    await importBtn.trigger('click');
    expect(importBtn.attributes('disabled')).toBeDefined();
    expect(addBtn.attributes('disabled')).toBeDefined();
    await importBtn.trigger('click');
    expect(importHolidays).toHaveBeenCalledTimes(1);
    finish({ imported: 16, skipped: 0, untranslated: [] });
    await flushPromises();
    expect(importBtn.attributes('disabled')).toBeUndefined();
    expect(addBtn.attributes('disabled')).toBeUndefined();
  });

  it('ger ikonknapparna tillgängliga namn', async () => {
    const { w } = await mountCard();
    expect(w.find('[data-testid="holiday-prev"]').attributes('aria-label')).toBe('Föregående år');
    expect(w.find('[data-testid="holiday-next"]').attributes('aria-label')).toBe('Nästa år');
    expect(w.find('[data-testid="remove-holiday-1"]').attributes('aria-label')).toBe('Ta bort Nyårsdagen');
  });

  it('tar bort utan dialog', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    const { w, dataStore } = await mountCard();
    await w.find('[data-testid="remove-holiday-2"]').trigger('click');
    await flushPromises();
    expect(confirmSpy).not.toHaveBeenCalled();
    expect(dataStore.removeHoliday).toHaveBeenCalledWith(2);
    expect(w.emitted('changed')).toHaveLength(1);
    confirmSpy.mockRestore();
  });

  it('lägger till först när datum och namn finns, och tömmer fälten', async () => {
    const { w, dataStore } = await mountCard();
    const add = w.find('[data-testid="add-holiday"]');
    expect(add.attributes('disabled')).toBeDefined();
    await w.find('[data-testid="new-holiday-name"]').setValue('Studiedag');
    expect(add.attributes('disabled')).toBeDefined();
    await typeDate(w, 'new-holiday-date', '2027-03-04');
    expect(add.attributes('disabled')).toBeUndefined();
    await add.trigger('click');
    await flushPromises();
    expect(dataStore.addHoliday).toHaveBeenCalledWith('2027-03-04', 'Studiedag');
    expect(w.emitted('changed')).toHaveLength(1);
    expect(w.find('[data-testid="new-holiday-name"]').element.value).toBe('');
    expect(w.find('[data-testid="add-holiday"]').attributes('disabled')).toBeDefined();
  });

  it('importerar och visar kvittot', async () => {
    const { w, dataStore } = await mountCard();
    const btn = w.find('[data-testid="import-holidays"]');
    expect(btn.text()).toBe('Importera svenska helgdagar för 2027');
    await btn.trigger('click');
    await flushPromises();
    expect(dataStore.importHolidays).toHaveBeenCalledWith(2027);
    expect(w.find('[data-testid="holiday-receipt"]').text()).toBe('16 importerade, 0 fanns redan.');
  });

  it('visar okända namn som en andra rad i kvittot', async () => {
    const { w } = await mountCard({
      importHolidays: vi.fn().mockResolvedValue({
        imported: 1, skipped: 2, untranslated: ['Foo Day', 'Bar Day'],
      }),
    });
    await w.find('[data-testid="import-holidays"]').trigger('click');
    await flushPromises();
    const text = w.find('[data-testid="holiday-receipt"]').text();
    expect(text).toContain('1 importerade, 2 fanns redan.');
    expect(text).toContain('Okända namn sparades på engelska: Foo Day, Bar Day.');
  });

  it('visar felet när importen rejektar', async () => {
    const { w } = await mountCard({
      importHolidays: vi.fn().mockRejectedValue({ message: 'Kunde inte hämta helgdagar just nu.' }),
    });
    await w.find('[data-testid="import-holidays"]').trigger('click');
    await flushPromises();
    expect(w.find('[data-testid="holiday-error"]').text()).toBe('Kunde inte hämta helgdagar just nu.');
  });

  it('tömmer felet när nästa åtgärd börjar', async () => {
    const importHolidays = vi.fn()
      .mockRejectedValueOnce({ message: 'Fel.' })
      .mockResolvedValueOnce({ imported: 1, skipped: 0, untranslated: [] });
    const { w } = await mountCard({ importHolidays });
    await w.find('[data-testid="import-holidays"]').trigger('click');
    await flushPromises();
    expect(w.find('[data-testid="holiday-error"]').exists()).toBe(true);
    await w.find('[data-testid="import-holidays"]').trigger('click');
    await flushPromises();
    expect(w.find('[data-testid="holiday-error"]').exists()).toBe(false);
  });

  it.each([
    ['namnbyte', async (w) => {
      const i = w.find('[data-testid="holiday-name-1"]');
      i.element.value = 'X';
      await i.trigger('change');
    }],
    ['borttagning', (w) => w.find('[data-testid="remove-holiday-1"]').trigger('click')],
    ['tillägg', async (w) => {
      await w.find('[data-testid="new-holiday-name"]').setValue('Dag');
      await typeDate(w, 'new-holiday-date', '2027-05-05');
      await w.find('[data-testid="add-holiday"]').trigger('click');
    }],
    ['import', (w) => w.find('[data-testid="import-holidays"]').trigger('click')],
  ])('laddar om listan och emittar changed efter %s', async (_name, act) => {
    const { w, dataStore } = await mountCard();
    await act(w);
    await flushPromises();
    expect(dataStore.listHolidays).toHaveBeenCalledTimes(2);
    expect(w.emitted('changed')).toHaveLength(1);
  });
});
