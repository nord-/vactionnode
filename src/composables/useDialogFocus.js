import { watch, nextTick } from 'vue';

// Fokus och Escape för dialogerna, som visas med open-attributet i stället
// för showModal(). showModal() lägger dialogen i webbläsarens top layer, och
// då hamnar datumväljarens popup (teleporterad till <body>) bakom den.
//
// - När dialogen öppnas flyttas fokus till dess första fält, och när den
//   stängs tillbaka dit fokus var innan.
// - Escape stänger dialogen, utom när det bara stänger en öppen
//   datumväljare: väljarens popup finns då kvar i DOM:en när händelsen
//   bubblar hit.
export function useDialogFocus(dialogRef, isOpen, close) {
  let returnTo = null;

  watch(isOpen, async (open) => {
    if (open) {
      returnTo = document.activeElement;
      await nextTick();
      dialogRef.value?.querySelector('input, select, textarea, button')?.focus();
    } else if (returnTo instanceof HTMLElement && returnTo !== document.body) {
      returnTo.focus();
      returnTo = null;
    }
  });

  function onKeydown(event) {
    if (event.key !== 'Escape') return;
    if (document.querySelector('.dp--menu-wrapper')) return;
    event.preventDefault();
    close();
  }

  return { onKeydown };
}
