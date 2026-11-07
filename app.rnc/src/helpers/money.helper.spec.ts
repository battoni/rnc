import { describe, expect, it } from 'vitest';
import { toCents } from './money.helper';

describe('toCents', () => {
  it('multiplies by 100 and rounds to the nearest cent', () => {
    expect(toCents(1234.56)).toBe(123456);
    expect(toCents(220)).toBe(22000);
  });

  it('absorbs floating point drift', () => {
    expect(toCents(0.1 + 0.2)).toBe(30);
    expect(toCents(19.99)).toBe(1999);
  });
});
