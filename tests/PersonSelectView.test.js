import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PersonSelectView from '../src/components/PersonSelectView.vue';

const persons = [{ id: 'p1', name: 'Anna', groupId: null }];

describe('PersonSelectView', () => {
  it('emittar select med valt id', async () => {
    const w = mount(PersonSelectView, { props: { persons } });
    await w.find('[data-testid="select-p1"]').trigger('click');
    expect(w.emitted('select')[0][0]).toBe('p1');
  });
  it('emittar create med nytt namn', async () => {
    const w = mount(PersonSelectView, { props: { persons } });
    await w.find('[data-testid="new-name"]').setValue('Björn');
    await w.find('[data-testid="create"]').trigger('click');
    expect(w.emitted('create')[0][0]).toBe('Björn');
  });
});
