import { describe, expect, it } from 'vitest';
import { formatReal } from './real.helper';

// Intl puts a non-breaking space after the currency symbol.
function normalizeSpaces(value: string): string {
  return value.replace(/\u00a0/g, ' ');
}

describe('formatReal', () => {
  it('formats integer cents as pt-BR reais with two decimals', () => {
    expect(normalizeSpaces(formatReal(123456))).toBe('R$ 1.234,56');
    expect(normalizeSpaces(formatReal(200000))).toBe('R$ 2.000,00');
  });

  it('formats zero and negative amounts', () => {
    expect(normalizeSpaces(formatReal(0))).toBe('R$ 0,00');
    expect(normalizeSpaces(formatReal(-96800))).toBe('-R$ 968,00');
  });
});
