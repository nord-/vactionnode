import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LoginView from '../src/components/LoginView.vue';

describe('LoginView', () => {
  it('emittar submit med inskrivet lösenord', async () => {
    const w = mount(LoginView, { props: { error: false } });
    await w.find('input[type="password"]').setValue('hemligt');
    await w.find('form').trigger('submit.prevent');
    expect(w.emitted('submit')[0][0]).toBe('hemligt');
  });
  it('visar felmeddelande när error är sant', () => {
    const w = mount(LoginView, { props: { error: true } });
    expect(w.text()).toContain('Fel lösenord');
  });
});
