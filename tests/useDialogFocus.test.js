import { describe, it, expect, afterEach } from 'vitest';
import { defineComponent, h, ref, nextTick } from 'vue';
import { mount, enableAutoUnmount } from '@vue/test-utils';
import { useDialogFocus } from '../src/composables/useDialogFocus.js';

enableAutoUnmount(afterEach);

function mountDialog() {
  const closed = [];
  const Dialog = defineComponent({
    props: { open: { type: Boolean, default: false } },
    setup(props) {
      const dialog = ref(null);
      const { onKeydown } = useDialogFocus(dialog, () => props.open, () => closed.push(true));
      return () => h('dialog', { ref: dialog, open: props.open, onKeydown }, [
        h('input', { 'data-testid': 'first' }),
        h('button', 'OK'),
      ]);
    },
  });
  const opener = document.createElement('button');
  document.body.appendChild(opener);
  opener.focus();
  const wrapper = mount(Dialog, { attachTo: document.body });
  return { wrapper, opener, closed };
}

describe('useDialogFocus', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('flyttar fokus till första fältet när dialogen öppnas', async () => {
    const { wrapper } = mountDialog();
    await wrapper.setProps({ open: true });
    await nextTick();
    expect(document.activeElement).toBe(wrapper.find('[data-testid="first"]').element);
  });

  it('lämnar tillbaka fokus till det som hade det när dialogen stängs', async () => {
    const { wrapper, opener } = mountDialog();
    await wrapper.setProps({ open: true });
    await nextTick();
    await wrapper.setProps({ open: false });
    await nextTick();
    expect(document.activeElement).toBe(opener);
  });

  it('stänger med Escape', async () => {
    const { wrapper, closed } = mountDialog();
    await wrapper.setProps({ open: true });
    await wrapper.find('[data-testid="first"]').trigger('keydown', { key: 'Escape' });
    expect(closed).toHaveLength(1);
  });

  it('stänger inte när Escape bara stänger en öppen datumväljare', async () => {
    const { wrapper, closed } = mountDialog();
    await wrapper.setProps({ open: true });
    const menu = document.createElement('div');
    menu.className = 'dp--menu-wrapper';
    document.body.appendChild(menu);
    await wrapper.find('[data-testid="first"]').trigger('keydown', { key: 'Escape' });
    expect(closed).toHaveLength(0);
  });
});
