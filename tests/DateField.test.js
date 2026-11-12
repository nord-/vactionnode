import { describe, it, expect, afterEach } from 'vitest';
import { mount, flushPromises, enableAutoUnmount } from '@vue/test-utils';
import DateField from '../src/components/DateField.vue';

enableAutoUnmount(afterEach);

function mountField(props = {}) {
  return mount(DateField, {
    props: { modelValue: '', locale: 'sv-SE', ...props },
    attrs: { 'data-testid': 'date' },
    attachTo: document.body,
  });
}

function input(w) {
  return w.find('[data-testid="date"] input');
}

// Skriver text och bekräftar med Enter eller lämnar fältet med Tab.
async function type(w, text, key = 'Enter') {
  const el = input(w);
  await el.trigger('focus');
  await el.setValue(text);
  await el.trigger('keydown', { key });
  await el.trigger('blur');
  await flushPromises();
}

describe('DateField', () => {
  it('visar värdet i språkets format', async () => {
    const sv = mountField({ modelValue: '2026-06-16' });
    const us = mountField({ modelValue: '2026-06-16', locale: 'en-US' });
    await flushPromises();

    expect(input(sv).element.value).toBe('2026-06-16');
    expect(input(us).element.value).toBe('6/16/2026');
  });

  it('emittar ISO-datum när texten går att tolka', async () => {
    const w = mountField({ locale: 'de-DE' });

    await type(w, '16.6.2026');

    expect(w.emitted('update:modelValue').at(-1)[0]).toBe('2026-06-16');
  });

  it('sätter värdet när fältet lämnas utan Enter', async () => {
    const w = mountField();

    await type(w, '2026-06-16', 'Tab');

    expect(w.emitted('update:modelValue').at(-1)[0]).toBe('2026-06-16');
  });

  it('visar fel när texten inte går att tolka och fältet lämnas', async () => {
    const w = mountField();

    await type(w, '2026-02-30', 'Escape');

    expect(w.text()).toContain('Ogiltigt datum');
  });

  it('visar inget fel medan man skriver', async () => {
    const w = mountField();
    const el = input(w);

    await el.trigger('focus');
    await el.setValue('2026-0');
    await flushPromises();

    expect(w.text()).not.toContain('Ogiltigt datum');
  });

  it('visar popupen på användarens språk med veckan från måndag', async () => {
    const w = mountField({ modelValue: '2026-06-16' });

    await input(w).trigger('focus');
    await input(w).trigger('click');
    await flushPromises();

    const text = document.body.textContent;
    expect(text).toContain('juni');
    expect(text.indexOf('må')).toBeLessThan(text.indexOf('sö'));
    // Veckokolumnens rubrik står direkt före måndag.
    expect(text).toContain('vmåtiontofrlösö');
  });

  it('visar ett nytt värde som kommer utifrån', async () => {
    const w = mountField({ modelValue: '2026-06-16' });

    await w.setProps({ modelValue: '2026-07-01' });
    await flushPromises();

    expect(input(w).element.value).toBe('2026-07-01');
  });
});
