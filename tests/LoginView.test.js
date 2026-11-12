import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LoginView from '../src/components/LoginView.vue';

describe('LoginView', () => {
  it('emittar submit med e-post och lösenord', async () => {
    const wrapper = mount(LoginView);

    await wrapper.find('[data-testid="email"]').setValue('anna@example.com');
    await wrapper.find('[data-testid="password"]').setValue('hemligt123');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      email: 'anna@example.com',
      password: 'hemligt123',
    });
  });

  it('visar serverns felmeddelande i stället för en fast text', () => {
    const wrapper = mount(LoginView, {
      props: { error: 'För många försök. Vänta en stund.' },
    });

    expect(wrapper.text()).toContain('För många försök. Vänta en stund.');
  });
});
