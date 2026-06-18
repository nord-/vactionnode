import { describe, it, expect } from 'vitest';
import { defaultRange } from '../src/composables/useDefaultRange.js';

describe('defaultRange', () => {
  it('ger innevarande månad + två framåt', () => {
    expect(defaultRange(new Date(2025, 5, 18))).toEqual({ from: '2025-06', to: '2025-08' });
  });
  it('hanterar årsskifte', () => {
    expect(defaultRange(new Date(2025, 10, 1))).toEqual({ from: '2025-11', to: '2026-01' });
  });
});
