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
  it('renderar en kolumn per dag i månaden, utfylld till hela veckor', () => {
    const w = mountGrid();
    // Juni 2025: 1:a är en söndag, 30:e en måndag -> 26 maj till 6 juli.
    expect(w.findAll('[data-testid="day-header"]').length).toBe(6 + 30 + 6);
  });
  it('tonar ned utfyllnadsdagar och lämnar deras månadsrubrik tom', () => {
    const w = mountGrid();
    const headers = w.findAll('[data-testid="day-header"]');
    expect(headers[0].classes()).toContain('opacity-60');
    expect(headers[6].classes()).not.toContain('opacity-60');
    const monthHeaders = w.findAll('thead tr')[0].findAll('th').slice(1).map((th) => th.text());
    expect(monthHeaders).toEqual(['', 'JUNI 2025', '']);
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
  it('emittar add-vacation med person och dag vid klick på en tom cell', async () => {
    const w = mountGrid({ canAddFor: (id) => id === 'p1' });
    const empty = w.findAll('[data-vacation="false"]')[0];
    expect(empty.classes()).toContain('cursor-pointer');
    await empty.trigger('click');
    expect(w.emitted('add-vacation')[0][0]).toEqual({ personId: 'p1', date: '2025-05-26' });
  });
  it('emittar inget vid klick på en tom cell som inte får läggas till', async () => {
    const w = mountGrid({ canAddFor: () => false });
    const empty = w.findAll('[data-vacation="false"]')[0];
    expect(empty.classes()).not.toContain('cursor-pointer');
    await empty.trigger('click');
    expect(w.emitted('add-vacation')).toBeUndefined();
  });
  it('håller ihop vecka 53 över årsskiftet när utfyllnaden börjar året innan', () => {
    const w = mountGrid({ fromMonth: '2027-01', toMonth: '2027-01', vacations: [] });
    const [monthRow, weekRow] = w.findAll('thead tr');
    const firstWeek = weekRow.findAll('th')[1];
    expect(firstWeek.text()).toBe('v.53');
    expect(firstWeek.attributes('colspan')).toBe('7');
    expect(monthRow.findAll('th').slice(1).map((th) => th.text())).toEqual(['', 'JANUARI 2027']);
  });
  it('visar pekarmarkör på en semestercell bara när den får öppnas', () => {
    const allowed = mountGrid({ canAddFor: () => true });
    const denied = mountGrid({ canAddFor: () => false });
    expect(allowed.find('[data-vacation="true"]').classes()).toContain('cursor-pointer');
    expect(denied.find('[data-vacation="true"]').classes()).not.toContain('cursor-pointer');
    expect(denied.find('[data-vacation="true"]').classes()).toContain('bg-success');
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
