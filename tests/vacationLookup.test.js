import { describe, it, expect } from 'vitest';
import { vacationForCell } from '../src/lib/vacationLookup.js';

const vacations = [
  { id: 'v1', personId: 'p1', start: '2025-06-16', end: '2025-06-20' },
  { id: 'v2', personId: 'p2', start: '2025-06-18', end: '2025-06-18' },
];

describe('vacationForCell', () => {
  it('hittar semester som täcker datumet (inklusiva kanter)', () => {
    expect(vacationForCell(vacations, 'p1', '2025-06-16').id).toBe('v1');
    expect(vacationForCell(vacations, 'p1', '2025-06-20').id).toBe('v1');
    expect(vacationForCell(vacations, 'p1', '2025-06-18').id).toBe('v1');
  });
  it('returnerar null utanför intervallet', () => {
    expect(vacationForCell(vacations, 'p1', '2025-06-21')).toBe(null);
    expect(vacationForCell(vacations, 'p1', '2025-06-15')).toBe(null);
  });
  it('matchar rätt person', () => {
    expect(vacationForCell(vacations, 'p2', '2025-06-18').id).toBe('v2');
    expect(vacationForCell(vacations, 'p2', '2025-06-16')).toBe(null);
  });
  it('returnerar hela posten även när datumet är en kant (för redigering vid fönsterkant)', () => {
    const v = vacationForCell(vacations, 'p1', '2025-06-20');
    expect(v).toMatchObject({ start: '2025-06-16', end: '2025-06-20' });
  });
});
