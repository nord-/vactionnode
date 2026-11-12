import { describe, it, expect } from 'vitest';
import { isoWeekNumber, isoWeekYear, enumerateMonths, monthSpanCount, generateColumns, weekAlignedRange } from '../src/lib/dates.js';

describe('isoWeekNumber', () => {
  it('2025-06-16 (måndag) är vecka 25', () => {
    expect(isoWeekNumber(new Date(2025, 5, 16))).toBe(25);
  });
  it('2025-01-01 (onsdag) tillhör vecka 1', () => {
    expect(isoWeekNumber(new Date(2025, 0, 1))).toBe(1);
  });
  it('2021-01-01 (fredag) tillhör vecka 53 föregående år', () => {
    expect(isoWeekNumber(new Date(2021, 0, 1))).toBe(53);
  });
  it('2023-01-01 (söndag) tillhör vecka 52 föregående år', () => {
    expect(isoWeekNumber(new Date(2023, 0, 1))).toBe(52);
  });
  it('2020-12-31 (torsdag) är vecka 53', () => {
    expect(isoWeekNumber(new Date(2020, 11, 31))).toBe(53);
  });
});

describe('isoWeekYear', () => {
  it('2025-12-29 (måndag) tillhör ISO-vecka 1 år 2026', () => {
    // ISO-vecka 1/2026 börjar 2025-12-29 — veckans torsdag ligger i 2026.
    expect(isoWeekNumber(new Date(2025, 11, 29))).toBe(1);
    expect(isoWeekYear(new Date(2025, 11, 29))).toBe(2026);
  });
  it('2021-01-01 (fredag) tillhör ISO-vecka 53 år 2020', () => {
    expect(isoWeekNumber(new Date(2021, 0, 1))).toBe(53);
    expect(isoWeekYear(new Date(2021, 0, 1))).toBe(2020);
  });
  it('2025-06-16 tillhör ISO-år 2025', () => {
    expect(isoWeekYear(new Date(2025, 5, 16))).toBe(2025);
  });
});

describe('enumerateMonths / monthSpanCount', () => {
  it('listar månader inklusivt', () => {
    expect(enumerateMonths('2025-06', '2025-08')).toEqual(['2025-06', '2025-07', '2025-08']);
  });
  it('hanterar årsskifte', () => {
    expect(enumerateMonths('2025-12', '2026-01')).toEqual(['2025-12', '2026-01']);
  });
  it('räknar spann inklusivt', () => {
    expect(monthSpanCount('2025-06', '2025-08')).toBe(3);
    expect(monthSpanCount('2025-06', '2025-06')).toBe(1);
  });
});

describe('generateColumns', () => {
  it('genererar en kolumn per dag, utfylld till hela veckor', () => {
    const cols = generateColumns('2025-06', '2025-08');
    expect(cols.length).toBe(6 + 30 + 31 + 31);
    expect(cols[0].iso).toBe('2025-05-26');
    expect(cols.at(-1).iso).toBe('2025-08-31');
    expect(cols.length % 7).toBe(0);
  });
  it('flaggar utfyllnadsdagar utanför valda månader', () => {
    const cols = generateColumns('2025-06', '2025-06');
    expect(cols.find((c) => c.iso === '2025-05-31').inSelectedMonths).toBe(false);
    expect(cols.find((c) => c.iso === '2025-06-01').inSelectedMonths).toBe(true);
    expect(cols.find((c) => c.iso === '2025-06-30').inSelectedMonths).toBe(true);
    expect(cols.find((c) => c.iso === '2025-07-01').inSelectedMonths).toBe(false);
  });
  it('flaggar helger och sätter måndag-index 0', () => {
    const cols = generateColumns('2025-06', '2025-06');
    const mon16 = cols.find((c) => c.iso === '2025-06-16');
    const sat21 = cols.find((c) => c.iso === '2025-06-21');
    const sun22 = cols.find((c) => c.iso === '2025-06-22');
    expect(mon16.weekdayIndex).toBe(0);
    expect(mon16.weekdayLabel).toBe('Må');
    expect(mon16.isWeekend).toBe(false);
    expect(sat21.isWeekend).toBe(true);
    expect(sun22.isWeekend).toBe(true);
  });
  it('sätter monthLabel, isoWeek och isoWeekYear', () => {
    const cols = generateColumns('2025-06', '2025-06');
    const mon16 = cols.find((c) => c.iso === '2025-06-16');
    expect(mon16.monthLabel).toBe('JUNI 2025');
    expect(mon16.monthKey).toBe('2025-06');
    expect(mon16.isoWeek).toBe(25);
    expect(mon16.isoWeekYear).toBe(2025);
  });
  it('sätter rätt isoWeekYear vid årsskifte', () => {
    const cols = generateColumns('2025-12', '2026-01');
    const dec29 = cols.find((c) => c.iso === '2025-12-29');
    expect(dec29.isoWeek).toBe(1);
    expect(dec29.isoWeekYear).toBe(2026);
  });
});

describe('weekAlignedRange', () => {
  it('fyller ut till måndag före och söndag efter', () => {
    expect(weekAlignedRange('2026-11', '2027-01')).toEqual({ from: '2026-10-26', to: '2027-01-31' });
    expect(weekAlignedRange('2026-09', '2026-11')).toEqual({ from: '2026-08-31', to: '2026-12-06' });
  });
  it('fyller inte ut en månad som börjar på måndag och slutar på söndag', () => {
    expect(weekAlignedRange('2027-02', '2027-02')).toEqual({ from: '2027-02-01', to: '2027-02-28' });
  });
  it('hanterar årsskifte och skottår', () => {
    expect(weekAlignedRange('2025-12', '2025-12')).toEqual({ from: '2025-12-01', to: '2026-01-04' });
    expect(weekAlignedRange('2024-02', '2024-02')).toEqual({ from: '2024-01-29', to: '2024-03-03' });
  });
  it('fyller ut bakåt in i föregående år', () => {
    // 2027-01-01 är en fredag; veckan börjar måndag 2026-12-28.
    expect(weekAlignedRange('2027-01', '2027-01')).toEqual({ from: '2026-12-28', to: '2027-01-31' });
  });
});

describe('generateColumns över årsskiftet bakåt', () => {
  it('lägger utfyllnaden i ISO-vecka 53 av föregående år', () => {
    const cols = generateColumns('2027-01', '2027-01');
    const dec28 = cols[0];
    const jan3 = cols.find((c) => c.iso === '2027-01-03');
    const jan4 = cols.find((c) => c.iso === '2027-01-04');
    expect(dec28).toMatchObject({ iso: '2026-12-28', isoWeek: 53, isoWeekYear: 2026, inSelectedMonths: false });
    expect(jan3).toMatchObject({ isoWeek: 53, isoWeekYear: 2026, inSelectedMonths: true });
    expect(jan4).toMatchObject({ isoWeek: 1, isoWeekYear: 2027 });
  });
});
