import { describe, it, expect } from 'vitest';
import { defaultRange, monthRangeToDates } from '../src/composables/useDefaultRange.js';

describe('defaultRange', () => {
  it('ger innevarande månad + två framåt', () => {
    expect(defaultRange(new Date(2025, 5, 18))).toEqual({ from: '2025-06', to: '2025-08' });
  });
  it('hanterar årsskifte', () => {
    expect(defaultRange(new Date(2025, 10, 1))).toEqual({ from: '2025-11', to: '2026-01' });
  });
});

it('konverterar månadsintervall till datum utfyllt till hela veckor', () => {
  expect(monthRangeToDates({ from: '2026-09', to: '2026-11' }))
    .toEqual({ from: '2026-08-31', to: '2026-12-06' });
});

it('hanterar februari under skottår', () => {
  expect(monthRangeToDates({ from: '2024-02', to: '2024-02' }))
    .toEqual({ from: '2024-01-29', to: '2024-03-03' });
});
