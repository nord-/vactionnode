import { describe, it, expect } from 'vitest';
import { formatDate, parseDate, monthNames, monthYearOrder } from '../src/lib/dateFormat.js';

describe('formatDate', () => {
  it('följer språkets datumformat', () => {
    expect(formatDate('2026-06-16', 'sv-SE')).toBe('2026-06-16');
    expect(formatDate('2026-06-16', 'en-US')).toBe('6/16/2026');
    expect(formatDate('2026-06-06', 'en-GB')).toBe('06/06/2026');
    expect(formatDate('2026-06-16', 'de-DE')).toBe('16.6.2026');
  });

  it('ger tom sträng för tomt eller ogiltigt värde', () => {
    expect(formatDate('', 'sv-SE')).toBe('');
    expect(formatDate(null, 'sv-SE')).toBe('');
    expect(formatDate('2026-02-30', 'sv-SE')).toBe('');
  });
});

describe('parseDate', () => {
  it('tolkar text i språkets ordning', () => {
    expect(parseDate('2026-06-16', 'sv-SE')).toBe('2026-06-16');
    expect(parseDate('6/16/2026', 'en-US')).toBe('2026-06-16');
    expect(parseDate('06/07/2026', 'en-GB')).toBe('2026-07-06');
    expect(parseDate('16.6.2026', 'de-DE')).toBe('2026-06-16');
  });

  it('tål blanksteg och andra skiljetecken', () => {
    expect(parseDate(' 2026 6 16 ', 'sv-SE')).toBe('2026-06-16');
    expect(parseDate('16/6/2026', 'de-DE')).toBe('2026-06-16');
  });

  it('godtar alltid ISO-format', () => {
    expect(parseDate('2026-06-16', 'en-US')).toBe('2026-06-16');
    expect(parseDate('2026-06-16', 'de-DE')).toBe('2026-06-16');
  });

  it('avvisar datum som inte finns', () => {
    expect(parseDate('2026-02-30', 'sv-SE')).toBeNull();
    expect(parseDate('2/30/2026', 'en-US')).toBeNull();
    expect(parseDate('2026-13-01', 'sv-SE')).toBeNull();
  });

  it('avvisar ofullständig eller felaktig inmatning', () => {
    expect(parseDate('', 'sv-SE')).toBeNull();
    expect(parseDate('2026-06', 'sv-SE')).toBeNull();
    expect(parseDate('26-06-16', 'sv-SE')).toBeNull();
    expect(parseDate('16 juni 2026', 'sv-SE')).toBeNull();
  });
});

describe('icke-gregorianska språk och egna tecken', () => {
  it('använder alltid den gregorianska kalendern', () => {
    expect(formatDate('2026-06-16', 'th-TH')).toContain('2026');
    expect(formatDate('2026-06-16', 'fa-IR')).toContain('2026');
    expect(parseDate(formatDate('2026-06-16', 'th-TH'), 'th-TH')).toBe('2026-06-16');
    expect(parseDate(formatDate('2026-06-16', 'fa-IR'), 'fa-IR')).toBe('2026-06-16');
  });

  it('läser tillbaka sitt eget format även med bokstäver och riktningstecken', () => {
    for (const locale of ['bg-BG', 'mk-MK', 'ar-EG', 'ar-SA']) {
      expect(parseDate(formatDate('2026-06-16', locale), locale), locale).toBe('2026-06-16');
    }
  });
});

describe('monthNames', () => {
  it('ger tolv månadsnamn på språket', () => {
    const sv = monthNames('sv-SE');
    expect(sv).toHaveLength(12);
    expect(sv[0]).toBe('januari');
    expect(sv[5]).toBe('juni');
    expect(monthNames('en-US')[5]).toBe('June');
  });
});

describe('monthYearOrder', () => {
  it('ger månad före år på svenska och engelska', () => {
    expect(monthYearOrder('sv-SE')).toEqual(['month', 'year']);
    expect(monthYearOrder('en-US')).toEqual(['month', 'year']);
  });

  it('ger år före månad där språket skriver så', () => {
    expect(monthYearOrder('ja-JP')).toEqual(['year', 'month']);
  });
});
