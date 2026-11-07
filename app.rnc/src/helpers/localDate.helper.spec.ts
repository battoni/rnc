import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fromDayMonthYear, toLocalDateString, toLocalDateTimeString } from './localDate.helper';

/*
 * The test runs in São Paulo on purpose. Under UTC — where CI usually sits — `toISOString()` and the
 * local getters agree, and a helper that used the wrong one would pass. `stubEnv` sets the zone Node
 * reads on every date call; `unstubAllEnvs` puts it back.
 */
beforeEach(() => vi.stubEnv('TZ', 'America/Sao_Paulo'));

afterEach(() => vi.unstubAllEnvs());

describe('toLocalDateString', () => {
  it('keeps the day the person sees after 21:00, when UTC is already tomorrow', () => {
    const NIGHT = new Date(2026, 8, 16, 22, 30);

    expect(NIGHT.toISOString().slice(0, 10)).toBe('2026-09-17');
    expect(toLocalDateString(NIGHT)).toBe('2026-09-16');
  });

  it('pads month and day', () => {
    expect(toLocalDateString(new Date(2026, 0, 5))).toBe('2026-01-05');
  });
});

describe('toLocalDateTimeString', () => {
  it('writes the wall clock, not UTC, and carries no offset', () => {
    expect(toLocalDateTimeString(new Date(2026, 8, 16, 22, 5, 9))).toBe('2026-09-16T22:05:09');
  });
});

describe('fromDayMonthYear', () => {
  it('reads a masked day as a local date', () => {
    const DATE = fromDayMonthYear('07/03/1990');

    expect(DATE && toLocalDateString(DATE)).toBe('1990-03-07');
  });

  it('refuses a day that does not exist instead of rolling it into the next month', () => {
    expect(fromDayMonthYear('31/02/1990')).toBeNull();
    expect(fromDayMonthYear('00/13/1990')).toBeNull();
  });

  it('refuses a value the mask did not finish', () => {
    expect(fromDayMonthYear('07/03/19__')).toBeNull();
    expect(fromDayMonthYear('')).toBeNull();
  });
});
