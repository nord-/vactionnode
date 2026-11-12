import { describe, it, expect } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import VacationModal from '../src/components/VacationModal.vue';
import { typeDate } from './helpers/dateField.js';
import DateField from '../src/components/DateField.vue';

function mountModal(extra = {}, options = {}) {
  return mount(VacationModal, {
    props: { modelValue: true, vacation: null, personName: 'Anna', ...extra },
    ...options,
  });
}

describe('VacationModal', () => {
  it('inaktiverar Spara vid ogiltigt intervall', async () => {
    const w = mountModal({}, { attachTo: document.body });
    await typeDate(w, 'start', '2025-06-20');
    await typeDate(w, 'end', '2025-06-16');
    await w.find('[data-testid="end"] input').trigger('keydown', { key: 'Escape' });
    await flushPromises();
    expect(w.find('[data-testid="save"]').attributes('disabled')).toBeDefined();
    expect(w.text()).toContain('Datumet kan inte vara före');
  });

  it('emittar save med giltigt intervall', async () => {
    const w = mountModal();
    await typeDate(w, 'start', '2025-06-16');
    await typeDate(w, 'end', '2025-06-20');
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2025-06-16', end: '2025-06-20' });
  });

  it('fyller i ett tomt slutdatum med startdatumet', async () => {
    const w = mountModal();
    await typeDate(w, 'start', '2026-12-14');
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2026-12-14', end: '2026-12-14' });
  });

  it('flyttar slutdatumet när startdatumet läggs efter det', async () => {
    const w = mountModal({ vacation: { id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' } });
    await typeDate(w, 'start', '2026-06-25');
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2026-06-25', end: '2026-06-25' });
  });

  it('behåller ett slutdatum som redan ligger efter startdatumet', async () => {
    const w = mountModal({ vacation: { id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' } });
    await typeDate(w, 'start', '2026-06-18');
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2026-06-18', end: '2026-06-20' });
  });

  it('spärrar dagar före startdatumet i slutdatumets kalender', async () => {
    const w = mountModal();
    await typeDate(w, 'start', '2026-12-14');
    const end = w.findAllComponents(DateField)[1];
    expect(end.props('min')).toBe('2026-12-14');
  });

  it('fyller i start- och slutdatum från startDate för en ny semester', async () => {
    const w = mountModal({ startDate: '2026-12-14' });
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2026-12-14', end: '2026-12-14' });
  });

  it('fyller i fälten på nytt varje gång modalen öppnas', async () => {
    const w = mountModal({ startDate: '2026-12-14' });
    await typeDate(w, 'start', '2026-12-20');
    await w.setProps({ modelValue: false });
    await w.setProps({ modelValue: true });
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2026-12-14', end: '2026-12-14' });
  });

  it('tömmer fälten när en ny semester öppnas utan startdatum', async () => {
    const w = mountModal();
    await typeDate(w, 'start', '2026-12-20');
    await w.setProps({ modelValue: false });
    await w.setProps({ modelValue: true });
    expect(w.find('[data-testid="save"]').attributes('disabled')).toBeDefined();
  });

  it('visar inget intervallfel i en tom modal men spärrar Spara', () => {
    const w = mountModal();
    expect(w.text()).not.toContain('Slutdatum måste vara');
    expect(w.find('[data-testid="save"]').attributes('disabled')).toBeDefined();
  });

  it('visar intervallfelet när slutdatum ligger före startdatum', () => {
    const w = mountModal({ vacation: { id: 'v1', personId: 'p1', start: '2026-06-20', end: '2026-06-16' } });
    expect(w.text()).toContain('Slutdatum måste vara samma som eller efter startdatum.');
  });

  it('glömmer ett datumfel när modalen öppnas igen', async () => {
    const w = mountModal({}, { attachTo: document.body });
    const start = w.find('[data-testid="start"] input');
    await start.trigger('focus');
    await start.setValue('abc');
    await start.trigger('keydown', { key: 'Escape' });
    await flushPromises();
    expect(w.text()).toContain('Ogiltigt datum');

    await w.setProps({ modelValue: false });
    await w.setProps({ modelValue: true });
    await flushPromises();

    expect(w.text()).not.toContain('Ogiltigt datum');
    w.unmount();
  });

  it('visar Ta bort endast vid redigering och emittar delete', async () => {
    const w = mountModal({ vacation: { id: 'v1', personId: 'p1', start: '2025-06-16', end: '2025-06-20' } });
    const del = w.find('[data-testid="delete"]');
    expect(del.exists()).toBe(true);
    await del.trigger('click');
    expect(w.emitted('delete')[0][0]).toEqual({ id: 'v1' });
  });

  it('döljer Ta bort vid ny semester', () => {
    const w = mountModal({ vacation: null });
    expect(w.find('[data-testid="delete"]').exists()).toBe(false);
  });
});
