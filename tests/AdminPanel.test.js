import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminPanel from '../src/components/AdminPanel.vue';

const groups = [{ id: 'g1', name: 'Team A' }];
const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];

describe('AdminPanel', () => {
  it('emittar add-group med namnet', async () => {
    const wrapper = mount(AdminPanel, { props: { groups, persons } });

    await wrapper.find('[data-testid="new-group"]').setValue('Team B');
    await wrapper.find('[data-testid="add-group"]').trigger('click');

    expect(wrapper.emitted('add-group')[0][0]).toBe('Team B');
  });

  it('emittar set-person-group vid gruppbyte', async () => {
    const wrapper = mount(AdminPanel, { props: { groups, persons } });

    await wrapper.find('[data-testid="person-group-p1"]').setValue('g1');

    expect(wrapper.emitted('set-person-group')[0][0]).toEqual({ id: 'p1', groupId: 'g1' });
  });

  it('emittar add-person med e-post, lösenord och adminflagga', async () => {
    const wrapper = mount(AdminPanel, { props: { groups, persons } });

    await wrapper.find('[data-testid="new-person"]').setValue('Bo');
    await wrapper.find('[data-testid="new-person-email"]').setValue('bo@example.com');
    await wrapper.find('[data-testid="new-person-password"]').setValue('hemligt123');
    await wrapper.find('[data-testid="new-person-admin"]').setValue(true);
    await wrapper.find('[data-testid="add-person"]').trigger('click');

    expect(wrapper.emitted('add-person')[0][0]).toEqual({
      name: 'Bo',
      email: 'bo@example.com',
      password: 'hemligt123',
      groupId: null,
      isAdmin: true,
    });
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

  it('emittar set-password med personens id och det nya lösenordet', async () => {
    const w = mount(AdminPanel, { props: { groups, persons } });

    await w.find('[data-testid="person-password-p1"]').setValue('nyttlosen123');
    await w.find('[data-testid="set-password-p1"]').trigger('click');

    expect(w.emitted('set-password')[0][0]).toEqual({ id: 'p1', password: 'nyttlosen123' });
  });

  it('inaktiverar Spara lösenord under 8 tecken', async () => {
    const w = mount(AdminPanel, { props: { groups, persons } });

    await w.find('[data-testid="person-password-p1"]').setValue('kort');

    expect(w.find('[data-testid="set-password-p1"]').attributes('disabled')).toBeDefined();
  });
});
