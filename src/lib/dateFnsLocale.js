import { sv, enUS, enGB, nb, da, fi, de } from 'date-fns/locale';

// Locale-objekt för datumväljarens popup (månadsnamn, veckodagar). Bara de
// språk som teamet använder importeras: hela date-fns/locale skulle göra
// bygget flera hundra kB större. Utöka tabellen vid behov.
const LOCALES = {
  sv,
  en: enUS,
  'en-us': enUS,
  'en-gb': enGB,
  nb,
  no: nb,
  da,
  fi,
  de,
};

// Hela koden först (en-GB), sedan bara språket (sv-SE -> sv), sist engelska.
export function dateFnsLocale(code) {
  const tag = (code ?? '').toLowerCase();
  return LOCALES[tag] ?? LOCALES[tag.split('-')[0]] ?? enUS;
}
