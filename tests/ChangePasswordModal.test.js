import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ChangePasswordModal from '../src/components/ChangePasswordModal.vue';

function mountOpen(props = {}) {
  return mount(ChangePasswordModal, { props: { modelValue: true, ...props } });
}

async function fill(wrapper, current, next, confirm) {
  await wrapper.find('[data-testid="current"]').setValue(current);
  await wrapper.find('[data-testid="new"]').setValue(next);
  await wrapper.find('[data-testid="confirm"]').setValue(confirm);
}

describe('ChangePasswordModal', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('är öppen bara när modelValue är true', async () => {
    const wrapper = mount(ChangePasswordModal, { props: { modelValue: false } });
    expect(wrapper.find('dialog').attributes('open')).toBeUndefined();

    await wrapper.setProps({ modelValue: true });
    expect(wrapper.find('dialog').attributes('open')).toBeDefined();
  });

  it('kräver att bekräftelsen matchar', async () => {
    const wrapper = mountOpen();

    await fill(wrapper, 'gammalt123', 'nytt123456', 'felstavat');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')).toBeUndefined();
    expect(wrapper.text()).toContain('stämmer inte överens');
  });

  it('emittar submit när fälten stämmer', async () => {
    const wrapper = mountOpen();

    await fill(wrapper, 'gammalt123', 'nytt123456', 'nytt123456');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      currentPassword: 'gammalt123',
      newPassword: 'nytt123456',
    });
  });

  it('visar serverns felmeddelande när bytet misslyckas', () => {
    const wrapper = mountOpen({ error: 'Fel nuvarande lösenord.' });

    expect(wrapper.text()).toContain('Fel nuvarande lösenord.');
  });

  it('stänger via Avbryt', async () => {
    const wrapper = mountOpen();

    await wrapper.find('[data-testid="cancel-change-password"]').trigger('click');

    expect(wrapper.emitted('update:modelValue')[0][0]).toBe(false);
  });

  it('tömmer fälten och stänger en stund efter ett lyckat byte', async () => {
    vi.useFakeTimers();
    const wrapper = mountOpen({ success: false });

    await fill(wrapper, 'gammalt123', 'nytt123456', 'nytt123456');
    await wrapper.find('form').trigger('submit.prevent');
    await wrapper.setProps({ success: true });

    expect(wrapper.text()).toContain('Lösenordet har bytts.');
    expect(wrapper.find('[data-testid="current"]').element.value).toBe('');
    expect(wrapper.find('[data-testid="new"]').element.value).toBe('');
    expect(wrapper.find('[data-testid="confirm"]').element.value).toBe('');
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();

    vi.advanceTimersByTime(1500);

    expect(wrapper.emitted('update:modelValue')[0][0]).toBe(false);
  });

  it('nollställer fält och felmeddelande när den öppnas igen', async () => {
    const wrapper = mountOpen();

    await fill(wrapper, 'gammalt123', 'nytt123456', 'felstavat');
    await wrapper.find('form').trigger('submit.prevent');
    await wrapper.setProps({ modelValue: false });
    await wrapper.setProps({ modelValue: true });

    expect(wrapper.find('[data-testid="current"]').element.value).toBe('');
    expect(wrapper.find('[data-testid="new"]').element.value).toBe('');
    expect(wrapper.find('[data-testid="confirm"]').element.value).toBe('');
    expect(wrapper.text()).not.toContain('stämmer inte överens');
  });
});
