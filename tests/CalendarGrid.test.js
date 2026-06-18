import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CalendarGrid from '../src/components/CalendarGrid.vue';

const groups = [{ id: 'g1', name: 'Team A' }];
const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];
const vacations = [{ id: 'v1', personId: 'p1', start: '2025-06-16', end: '2025-06-18' }];

function mountGrid(extra = {}) {
  return mount(CalendarGrid, {
    props: { groups, persons, vacations, fromMonth: '2025-06', toMonth: '2025-06', ...extra },
  });
}

describe('CalendarGrid', () => {
  it('visar gruppnamn och personnamn', () => {
    const w = mountGrid();
    expect(w.text()).toContain('Team A');
    expect(w.text()).toContain('Anna');
  });
  it('renderar en kolumn per dag i månaden', () => {
    const w = mountGrid();
    expect(w.findAll('[data-testid="day-header"]').length).toBe(30);
  });
  it('markerar semesterceller', () => {
    const w = mountGrid();
    expect(w.findAll('[data-vacation="true"]').length).toBe(3);
  });
  it('emittar edit-vacation vid klick på semestercell', async () => {
    const w = mountGrid();
    await w.find('[data-vacation="true"]').trigger('click');
    expect(w.emitted('edit-vacation')[0][0].vacation.id).toBe('v1');
  });
  it('filtrerar på grupp', () => {
    const w = mountGrid({
      groups: [{ id: 'g1', name: 'Team A' }, { id: 'g2', name: 'Team B' }],
      persons: [{ id: 'p1', name: 'Anna', groupId: 'g1' }, { id: 'p2', name: 'Björn', groupId: 'g2' }],
      groupFilterId: 'g2',
    });
    expect(w.text()).toContain('Björn');
    expect(w.text()).not.toContain('Anna');
  });
});
