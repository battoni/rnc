import { describe, expect, it } from 'vitest';
import { shiftMonth } from './useLedgerMonth';

/* The ledger's ◀ ▶ (rnc-2 R11): whole months, across a year's turn both ways. */
describe('shiftMonth', () => {
  it.each([
    ['2026-11', -1, '2026-10'],
    ['2026-11', 1, '2026-12'],
    ['2026-12', 1, '2027-01'],
    ['2026-01', -1, '2025-12'],
    ['2026-03', -14, '2025-01'],
  ])('moves %s by %i to %s', (month, by, expected) => {
    expect(shiftMonth(month, by)).toBe(expected);
  });
});
