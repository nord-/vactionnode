import { describe, it, expect } from 'vitest';
import { sv, enUS, enGB, de } from 'date-fns/locale';
import { dateFnsLocale } from '../src/lib/dateFnsLocale.js';

describe('dateFnsLocale', () => {
  it('matchar hela språkkoden', () => {
    expect(dateFnsLocale('en-GB')).toBe(enGB);
    expect(dateFnsLocale('en-US')).toBe(enUS);
  });

  it('faller tillbaka på språket utan region', () => {
    expect(dateFnsLocale('sv-SE')).toBe(sv);
    expect(dateFnsLocale('sv-FI')).toBe(sv);
    expect(dateFnsLocale('de-AT')).toBe(de);
  });

  it('bryr sig inte om skiftläge', () => {
    expect(dateFnsLocale('sv-se')).toBe(sv);
    expect(dateFnsLocale('EN-gb')).toBe(enGB);
  });

  it('ger engelska för språk som inte finns i tabellen', () => {
    expect(dateFnsLocale('xx-YY')).toBe(enUS);
    expect(dateFnsLocale(undefined)).toBe(enUS);
  });
});
