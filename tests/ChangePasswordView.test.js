import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ChangePasswordView from '../src/components/ChangePasswordView.vue';

describe('ChangePasswordView', () => {
  it('kräver att bekräftelsen matchar', async () => {
    const wrapper = mount(ChangePasswordView);

    await wrapper.find('[data-testid="current"]').setValue('gammalt123');
    await wrapper.find('[data-testid="new"]').setValue('nytt123456');
    await wrapper.find('[data-testid="confirm"]').setValue('felstavat');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')).toBeUndefined();
    expect(wrapper.text()).toContain('stämmer inte överens');
  });

  it('emittar submit när fälten stämmer', async () => {
    const wrapper = mount(ChangePasswordView);

    await wrapper.find('[data-testid="current"]').setValue('gammalt123');
    await wrapper.find('[data-testid="new"]').setValue('nytt123456');
    await wrapper.find('[data-testid="confirm"]').setValue('nytt123456');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      currentPassword: 'gammalt123',
      newPassword: 'nytt123456',
    });
  });

  it('visar serverns felmeddelande när bytet misslyckas', async () => {
    const wrapper = mount(ChangePasswordView, { props: { error: 'Fel nuvarande lösenord.' } });

    expect(wrapper.text()).toContain('Fel nuvarande lösenord.');
  });

  it('tömmer fälten när bytet lyckas', async () => {
    const wrapper = mount(ChangePasswordView, { props: { success: false } });

    await wrapper.find('[data-testid="current"]').setValue('gammalt123');
    await wrapper.find('[data-testid="new"]').setValue('nytt123456');
    await wrapper.find('[data-testid="confirm"]').setValue('nytt123456');
    await wrapper.find('form').trigger('submit.prevent');

    await wrapper.setProps({ success: true });

    expect(wrapper.find('[data-testid="current"]').element.value).toBe('');
    expect(wrapper.find('[data-testid="new"]').element.value).toBe('');
    expect(wrapper.find('[data-testid="confirm"]').element.value).toBe('');
  });
});
