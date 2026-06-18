import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '../src/App.vue';

describe('App', () => {
  it('renderar rubriken', () => {
    const wrapper = mount(App);
    expect(wrapper.text()).toContain('Semesterkalender');
  });
});
