import { describe, it, expect, afterEach } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { parseHash, useHashRoute } from '../src/composables/useHashRoute.js';

describe('parseHash', () => {
  it('ger / för tom hash', () => {
    expect(parseHash('')).toBe('/');
    expect(parseHash('#')).toBe('/');
  });

  it('plockar ut sökvägen efter #', () => {
    expect(parseHash('#/')).toBe('/');
    expect(parseHash('#/admin')).toBe('/admin');
  });
});

describe('useHashRoute', () => {
  afterEach(() => {
    window.location.hash = '';
  });

  function mountProbe() {
    let route;
    const Probe = defineComponent({
      setup() {
        route = useHashRoute();
        return () => h('div', route.value);
      },
    });
    const wrapper = mount(Probe);
    return { wrapper, route: () => route.value };
  }

  it('läser hashen vid start', () => {
    window.location.hash = '#/admin';
    const { route } = mountProbe();

    expect(route()).toBe('/admin');
  });

  it('följer hashchange', async () => {
    const { wrapper, route } = mountProbe();

    window.location.hash = '#/admin';
    window.dispatchEvent(new HashChangeEvent('hashchange'));
    await nextTick();

    expect(route()).toBe('/admin');
    expect(wrapper.text()).toBe('/admin');
  });
});
