import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import VacationModal from '../src/components/VacationModal.vue';

function mountModal(extra = {}) {
  return mount(VacationModal, {
    props: { modelValue: true, vacation: null, personName: 'Anna', ...extra },
  });
}

describe('VacationModal', () => {
  it('inaktiverar Spara vid ogiltigt intervall', async () => {
    const w = mountModal();
    await w.find('[data-testid="start"]').setValue('2025-06-20');
    await w.find('[data-testid="end"]').setValue('2025-06-16');
    expect(w.find('[data-testid="save"]').attributes('disabled')).toBeDefined();
  });

  it('emittar save med giltigt intervall', async () => {
    const w = mountModal();
    await w.find('[data-testid="start"]').setValue('2025-06-16');
    await w.find('[data-testid="end"]').setValue('2025-06-20');
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2025-06-16', end: '2025-06-20' });
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
