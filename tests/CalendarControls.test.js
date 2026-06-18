import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CalendarControls from '../src/components/CalendarControls.vue';

const groups = [{ id: 'g1', name: 'Team A' }];

function mountControls(extra = {}) {
  return mount(CalendarControls, {
    props: { fromMonth: '2025-06', toMonth: '2025-08', groups, groupFilterId: 'all', ...extra },
  });
}

describe('CalendarControls', () => {
  it('klampar spannet till 3 månader när to ändras', async () => {
    const w = mountControls();
    const inputs = w.findAll('input[type="month"]');
    await inputs[1].setValue('2025-12');
    const ev = w.emitted('update:range').at(-1)[0];
    expect(ev).toEqual({ from: '2025-10', to: '2025-12' });
  });

  it('emittar gruppfilter', async () => {
    const w = mountControls();
    await w.find('select').setValue('g1');
    expect(w.emitted('update:groupFilter').at(-1)[0]).toBe('g1');
  });

  it('emittar add-vacation', async () => {
    const w = mountControls();
    await w.find('[data-testid="add-vacation"]').trigger('click');
    expect(w.emitted('add-vacation')).toBeTruthy();
  });
});
