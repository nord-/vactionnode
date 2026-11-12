import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MonthField from '../src/components/MonthField.vue';

function mountField(props = {}) {
  return mount(MonthField, { props: { modelValue: '2026-06', locale: 'sv-SE', ...props } });
}

describe('MonthField', () => {
  it('visar månadsnamn på språket och valt år', () => {
    const w = mountField();
    const select = w.find('select');

    expect(select.findAll('option').map((o) => o.text())[5]).toBe('juni');
    expect(select.element.value).toBe('06');
    expect(w.find('input[type="number"]').element.value).toBe('2026');
  });

  it('emittar YYYY-MM när månaden byts', async () => {
    const w = mountField();

    await w.find('select').setValue('09');

    expect(w.emitted('update:modelValue').at(-1)[0]).toBe('2026-09');
  });

  it('emittar YYYY-MM när året byts', async () => {
    const w = mountField();

    await w.find('input[type="number"]').setValue('2027');

    expect(w.emitted('update:modelValue').at(-1)[0]).toBe('2027-06');
  });

  it('emittar inget för ett ofullständigt år', async () => {
    const w = mountField();

    await w.find('input[type="number"]').setValue('202');

    expect(w.emitted('update:modelValue')).toBeUndefined();
  });

  it('återställer ett ofullständigt år när fältet lämnas', async () => {
    const w = mountField();
    const year = w.find('input[type="number"]');

    await year.setValue('202');
    await year.trigger('change');

    expect(year.element.value).toBe('2026');
    expect(w.emitted('update:modelValue')).toBeUndefined();
  });

  it('ställer året före månaden där språket gör det', () => {
    const w = mountField({ locale: 'ja-JP' });
    const controls = w.findAll('select, input[type="number"]');

    expect(controls[0].element.tagName).toBe('INPUT');
    expect(controls[1].element.tagName).toBe('SELECT');
  });
});
