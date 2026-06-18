import { describe, it, expect } from 'vitest';
import { isoWeekNumber, isoWeekYear, enumerateMonths, monthSpanCount, generateColumns } from '../src/lib/dates.js';

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
  it('genererar en kolumn per dag i intervallet', () => {
    const cols = generateColumns('2025-06', '2025-08');
    expect(cols.length).toBe(30 + 31 + 31);
    expect(cols[0].iso).toBe('2025-06-01');
    expect(cols.at(-1).iso).toBe('2025-08-31');
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
