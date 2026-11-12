import { flushPromises } from '@vue/test-utils';

// Skriver ett datum i ett DateField (data-testid sitter på omslaget) och
// bekräftar med Enter, som en användare gör i datumväljarens textfält.
export async function typeDate(wrapper, testid, text) {
  const input = wrapper.find(`[data-testid="${testid}"] input`);
  await input.trigger('focus');
  await input.setValue(text);
  await input.trigger('keydown', { key: 'Enter' });
  await flushPromises();
}
