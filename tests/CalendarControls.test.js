import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CalendarControls from '../src/components/CalendarControls.vue';
import MonthField from '../src/components/MonthField.vue';

const groups = [{ id: 'g1', name: 'Team A' }];

function mountControls(extra = {}) {
  return mount(CalendarControls, {
    props: { fromMonth: '2025-06', toMonth: '2025-08', groups, groupFilterId: 'all', ...extra },
  });
}

describe('CalendarControls', () => {
  afterEach(() => vi.useRealTimers());

  it('klampar spannet till 3 månader när to ändras', async () => {
    const w = mountControls();
    const to = w.findAllComponents(MonthField)[1];
    await to.find('select').setValue('12');
    const ev = w.emitted('update:range').at(-1)[0];
    expect(ev).toEqual({ from: '2025-10', to: '2025-12' });
  });

  it('emittar gruppfilter', async () => {
    const w = mountControls();
    await w.find('[data-testid="group-filter"]').setValue('g1');
    expect(w.emitted('update:groupFilter').at(-1)[0]).toBe('g1');
  });

  it('stegar hela intervallet en månad framåt och bakåt', async () => {
    const w = mountControls();
    await w.find('[data-testid="next-month"]').trigger('click');
    expect(w.emitted('update:range').at(-1)[0]).toEqual({ from: '2025-07', to: '2025-09' });
    await w.find('[data-testid="prev-month"]').trigger('click');
    expect(w.emitted('update:range').at(-1)[0]).toEqual({ from: '2025-05', to: '2025-07' });
  });

  it('Idag hoppar tillbaka till innevarande månad med standardspannet', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 10, 27));
    const w = mountControls({ fromMonth: '2025-06', toMonth: '2025-08' });
    await w.find('[data-testid="today"]').trigger('click');
    expect(w.emitted('update:range').at(-1)[0]).toEqual({ from: '2026-11', to: '2027-01' });
  });

  it('emittar add-vacation', async () => {
    const w = mountControls();
    await w.find('[data-testid="add-vacation"]').trigger('click');
    expect(w.emitted('add-vacation')).toBeTruthy();
  });
});
