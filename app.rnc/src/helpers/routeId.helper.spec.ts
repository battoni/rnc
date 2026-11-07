import { describe, expect, it } from 'vitest';
import { toRouteId } from './routeId.helper';

/*
 * The API sends ids as integers (lifeshifter-269) and a route param is always a string. This is the
 * one place the two meet; if it passed the string through, `idea.id === ideaId` would never match.
 */
describe('toRouteId', () => {
  it('turns a route param into the number the API sends', () => {
    expect(toRouteId('42')).toBe(42);
  });

  it('reads the first value of a repeated query key', () => {
    expect(toRouteId(['7', '8'])).toBe(7);
  });

  it.each([['abc'], [''], ['0'], ['-3'], ['1.5'], [undefined], [null], [12]])('names nothing for %j', (value) => {
    expect(toRouteId(value)).toBeNaN();
  });
});
