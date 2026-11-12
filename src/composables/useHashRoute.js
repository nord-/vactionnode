import { ref, onMounted, onUnmounted } from 'vue';

// Sökvägen efter # i adressfältet, t.ex. '#/admin' -> '/admin'. Tom hash
// betyder startsidan.
export function parseHash(hash) {
  return hash.replace(/^#/, '') || '/';
}

// Hashbaserad sidväxling i stället för vue-router: med bara två sidor räcker
// en ref som följer hashchange. Bakåtknapp, omladdning och direktlänkar
// fungerar utan att webbservern behöver några omskrivningsregler.
export function useHashRoute() {
  const route = ref(parseHash(window.location.hash));
  const update = () => {
    route.value = parseHash(window.location.hash);
  };

  onMounted(() => window.addEventListener('hashchange', update));
  onUnmounted(() => window.removeEventListener('hashchange', update));

  return route;
}
