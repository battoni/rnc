import { afterEach, describe, expect, it, vi } from 'vitest';
import prefersReducedMotion from './prefersReducedMotion.helper';

function stubMatchMedia(matches: boolean) {
  vi.stubGlobal('matchMedia', (query: string) => ({ matches, media: query }));
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('prefersReducedMotion', () => {
  it('is true when the system asks for less motion', () => {
    stubMatchMedia(true);

    expect(prefersReducedMotion()).toBe(true);
  });

  it('is false when the system has no preference', () => {
    stubMatchMedia(false);

    expect(prefersReducedMotion()).toBe(false);
  });

  it('is false where matchMedia does not exist, so motion plays rather than throws', () => {
    vi.stubGlobal('matchMedia', undefined);

    expect(prefersReducedMotion()).toBe(false);
  });
});
