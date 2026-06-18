import { describe, it, expect } from 'vitest';
import { isValidVacationRange, clampMonthRange } from '../src/lib/validation.js';

describe('isValidVacationRange', () => {
  it('godtar en enda dag', () => {
    expect(isValidVacationRange('2025-06-16', '2025-06-16')).toBe(true);
  });
  it('godtar end efter start', () => {
    expect(isValidVacationRange('2025-06-16', '2025-06-20')).toBe(true);
  });
  it('avvisar end före start', () => {
    expect(isValidVacationRange('2025-06-20', '2025-06-16')).toBe(false);
  });
  it('har ingen övre gräns', () => {
    expect(isValidVacationRange('2025-01-01', '2025-12-31')).toBe(true);
  });
});

describe('clampMonthRange', () => {
  it('lämnar oförändrat när spannet är inom max', () => {
    expect(clampMonthRange('2025-06', '2025-08', 'to')).toEqual({ from: '2025-06', to: '2025-08' });
  });
  it('när to ändras för långt fram flyttas from upp', () => {
    expect(clampMonthRange('2025-06', '2025-10', 'to')).toEqual({ from: '2025-08', to: '2025-10' });
  });
  it('när from ändras för långt bak flyttas to ned', () => {
    expect(clampMonthRange('2025-02', '2025-10', 'from')).toEqual({ from: '2025-02', to: '2025-04' });
  });
  it('hanterar to före from genom att klampa den ändrade kanten', () => {
    expect(clampMonthRange('2025-06', '2025-04', 'to')).toEqual({ from: '2025-04', to: '2025-04' });
  });
});
