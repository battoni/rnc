import { describe, expect, it } from 'vitest';
import { brandPalette, contrastRatio } from './brandPalette';

/*
 * R3 (rnc-3 F2): a sub-brand re-themes the site from two colours. The ramp keeps Franco's shape —
 * the primary is the 500 (the fill under white text), the accent the 300 (the highlight, and the
 * brand word on a dark ground) — and every pair the site paints still reads, in both schemes, for
 * any accent the operator picks: one that would not read is lightened until it does.
 */
const DARK_PANEL = '#141413';
/** The dark scheme's canvas (battoni-dev), under the back office's links. */
const DARK_CANVAS = '#0a0a09';
const WHITE = '#ffffff';
const AA = 4.5;

const BRANDS = {
  express: ['#3b2a86', '#ff8a3d'],
  prime: ['#24262b', '#c9a35a'],
  // An accent nobody should pick: as dark as the primary. It must still come out readable.
  murky: ['#1d3b2a', '#20302a'],
  // A primary right at the API's floor (white on it 4,54:1): the hero's tints must go almost white.
  atTheFloor: ['#767676', '#ff8a3d'],
  // rnc-4: the firms' own rows, painted on the back office too.
  franco: ['#00375a', '#14c3ff'],
  alvorada: ['#0f5c4d', '#f4b63f'],
} as const;

describe('contrastRatio', () => {
  it('measures WCAG 2 contrast, 21:1 black on white, 1:1 a colour on itself', () => {
    expect(contrastRatio('#000000', WHITE)).toBeCloseTo(21, 5);
    expect(contrastRatio('#3b2a86', '#3b2a86')).toBeCloseTo(1, 5);
    // Franco's own navy under white, as the theme measured it (franco.css).
    expect(contrastRatio(WHITE, '#00375a')).toBeCloseTo(12.4, 1);
  });
});

describe('brandPalette', () => {
  it("puts the primary on 500 and the accent on 300, Franco's ramp shape", () => {
    const palette = brandPalette('#3b2a86', '#ff8a3d');

    expect(palette['--color-primary-500']).toBe('#3b2a86');
    expect(palette['--color-primary-300']).toBe('#ff8a3d');
    expect(Object.keys(palette)).toEqual(
      [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((step) => `--color-primary-${step}`)
    );
  });

  it('darkens from 500 to 950 and lightens from 300 to 50', () => {
    const palette = brandPalette('#3b2a86', '#ff8a3d');
    const darkness = (step: number) => contrastRatio(WHITE, palette[`--color-primary-${step}`]!);

    [600, 700, 800, 900, 950].reduce((previous, step) => {
      expect(darkness(step)).toBeGreaterThan(darkness(previous));

      return step;
    }, 500);

    [200, 100, 50].reduce((previous, step) => {
      expect(darkness(step)).toBeLessThan(darkness(previous));

      return step;
    }, 300);
  });

  it.each(Object.entries(BRANDS))('keeps every pair the site paints readable for %s', (_, [primary, accent]) => {
    const palette = brandPalette(primary, accent);
    const step = (value: number) => palette[`--color-primary-${value}`]!;

    // The header CTA and the logo tile: white on the fill (the API's own rule, D4).
    expect(contrastRatio(WHITE, step(500))).toBeGreaterThanOrEqual(AA);
    // The hero CTA on the band and its hover: the deepest step on the accent.
    expect(contrastRatio(step(950), step(300))).toBeGreaterThanOrEqual(AA);
    expect(contrastRatio(step(950), step(200))).toBeGreaterThanOrEqual(AA);
    // The brand word in the dark scheme (`--color-primary-text` is the 300 there).
    expect(contrastRatio(step(300), DARK_PANEL)).toBeGreaterThanOrEqual(AA);
    // The dark scheme's link (`celer-button-link-primary` is the 400 there), on the canvas and the
    // panel (rnc-4 F2b: the back office paints the firm's ramp now).
    expect(contrastRatio(step(400), DARK_CANVAS)).toBeGreaterThanOrEqual(AA);
    expect(contrastRatio(step(400), DARK_PANEL)).toBeGreaterThanOrEqual(AA);
    // The hero lede and kicker on the band's darkest and lightest ends.
    expect(contrastRatio(step(100), step(500))).toBeGreaterThanOrEqual(AA);
    expect(contrastRatio(step(200), step(500))).toBeGreaterThanOrEqual(AA);
    // Tinted chips: the deepest step on the palest tints.
    expect(contrastRatio(step(950), step(50))).toBeGreaterThanOrEqual(AA);
    expect(contrastRatio(step(950), step(100))).toBeGreaterThanOrEqual(AA);
  });

  it('lightens an accent that would not read, and leaves a good one as it is', () => {
    expect(brandPalette('#1d3b2a', '#20302a')['--color-primary-300']).not.toBe('#20302a');
    expect(brandPalette('#24262b', '#c9a35a')['--color-primary-300']).toBe('#c9a35a');
  });

  it('reads either case and answers lower-case hex', () => {
    expect(brandPalette('#3B2A86', '#FF8A3D')['--color-primary-500']).toBe('#3b2a86');
  });
});
