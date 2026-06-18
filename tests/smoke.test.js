import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '../src/App.vue';

describe('App', () => {
  it('visar inloggningsvyn när man inte är autentiserad', () => {
    localStorage.clear();
    const wrapper = mount(App);
    expect(wrapper.text()).toContain('Logga in');
  });
});
