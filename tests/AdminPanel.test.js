import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminPanel from '../src/components/AdminPanel.vue';

const groups = [{ id: 'g1', name: 'Team A' }];
const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];

describe('AdminPanel', () => {
  it('emittar add-group', async () => {
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="new-group"]').setValue('Team B');
    await w.find('[data-testid="add-group"]').trigger('click');
    expect(w.emitted('add-group')[0][0]).toBe('Team B');
  });

  it('emittar add-person med vald grupp', async () => {
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="new-person"]').setValue('Björn');
    await w.find('[data-testid="new-person-group"]').setValue('g1');
    await w.find('[data-testid="add-person"]').trigger('click');
    expect(w.emitted('add-person')[0][0]).toEqual({ name: 'Björn', groupId: 'g1' });
  });

  it('bekräftar och emittar remove-person', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="remove-person-p1"]').trigger('click');
    expect(w.emitted('remove-person')[0][0]).toBe('p1');
    window.confirm.mockRestore();
  });

  it('avbryter remove-person när confirm avvisas', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="remove-person-p1"]').trigger('click');
    expect(w.emitted('remove-person')).toBeFalsy();
    window.confirm.mockRestore();
  });
});
