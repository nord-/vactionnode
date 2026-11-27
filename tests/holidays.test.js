import { describe, it, expect } from 'vitest';
import { holidayMap } from '../src/lib/holidays.js';

describe('holidayMap', () => {
  it('slår upp namnet på datumet', () => {
    const map = holidayMap([{ date: '2027-06-25', name: 'Midsommarafton' }]);
    expect(map.get('2027-06-25')).toBe('Midsommarafton');
  });
  it('ger en tom Map för en tom lista', () => {
    expect(holidayMap([]).size).toBe(0);
  });
});
