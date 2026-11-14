/*
 * A sub-brand's palette (rnc-3 R3), from the two colours the operator keeps: the same ramp shape as
 * Franco's own theme (`styles/theme/themes/franco.css`) — the primary is the 500, the fill under white
 * text; the accent is the 300, the highlight and the brand word on a dark ground; the pale steps are
 * the accent's tints and the deep ones the primary's shades. Pure: hex in, `--color-primary-*` out.
 *
 * Every pair the site paints is measured here, not hoped for. The API already holds white on the
 * primary to 4,5:1 (D4); the accent is free, so one that would not read on the deepest step or on the
 * dark scheme's panel is lightened until it does, and the tints the hero writes on the band (100,
 * 200) are lightened until they read on the 500. The 400 — the dark scheme's link in the back office,
 * which paints the firm's ramp since rnc-4 — is lightened until it reads on the dark canvas and panel.
 */
type Rgb = [number, number, number];

const AA_MINIMUM = 4.5;

/** The dark scheme's panel (battoni-dev's `surface-950`), where the 300 is the brand word. */
const DARK_PANEL: Rgb = [0x14, 0x14, 0x13];

/** The dark scheme's canvas, under the back office's links (the 400, `celer-button-link-primary`). */
const DARK_CANVAS: Rgb = [0x0a, 0x0a, 0x09];

const BLACK: Rgb = [0, 0, 0];
const WHITE: Rgb = [255, 255, 255];

/** How far toward black each shade of the primary goes. */
const SHADES = { 600: 0.15, 700: 0.3, 800: 0.45, 900: 0.6, 950: 0.75 } as const;

/** One lightening step, and the most of them it takes to reach white. */
const LIGHTEN_STEP = 0.1;
const LIGHTEN_LIMIT = 10;

/** The sRGB channel below which the curve is linear (WCAG 2.x), as `Contrast.php` in the API. */
const LINEAR_THRESHOLD = 0.03928;

// HELPERS
function parse(hex: string): Rgb {
  const value = hex.replace('#', '');

  return [0, 2, 4].map((start) => parseInt(value.slice(start, start + 2), 16)) as Rgb;
}

function format(rgb: Rgb): string {
  return `#${rgb.map((channel) => Math.round(channel).toString(16).padStart(2, '0')).join('')}`;
}

function mix(from: Rgb, to: Rgb, weight: number): Rgb {
  return from.map((channel, index) => channel + (to[index]! - channel) * weight) as Rgb;
}

function luminance(rgb: Rgb): number {
  const [red, green, blue] = rgb.map((channel) => {
    const srgb = Math.round(channel) / 255;

    return srgb <= LINEAR_THRESHOLD ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
  }) as Rgb;

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function ratio(first: Rgb, second: Rgb): number {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);

  return (lighter! + 0.05) / (darker! + 0.05);
}

/** Toward white, a step at a time, until `colour` reads on every ground given (or is white). */
function lightenUntilReadable(colour: Rgb, grounds: Rgb[]): Rgb {
  let current = colour;

  for (let step = 0; step < LIGHTEN_LIMIT; step++) {
    if (grounds.every((ground) => ratio(current, ground) >= AA_MINIMUM)) return current;

    current = mix(current, WHITE, LIGHTEN_STEP * (step + 1));
  }

  return WHITE;
}

/** WCAG 2 contrast between two `#rrggbb` colours, from 1 (none) to 21 (black on white). */
function contrastRatio(first: string, second: string): number {
  return ratio(parse(first), parse(second));
}

/** The `--color-primary-*` ramp of a sub-brand, 50 to 950, as lower-case hex. */
function brandPalette(primary: string, accent: string): Record<string, string> {
  const base = parse(primary);
  const deepest = mix(base, BLACK, SHADES[950]);
  const highlight = lightenUntilReadable(parse(accent), [deepest, DARK_PANEL]);
  const tint200 = lightenUntilReadable(mix(highlight, WHITE, 0.45), [base]);
  const tint100 = lightenUntilReadable(mix(tint200, WHITE, 0.45), [base]);
  const link = lightenUntilReadable(mix(highlight, base, 0.5), [DARK_CANVAS, DARK_PANEL]);

  return {
    '--color-primary-50': format(mix(tint100, WHITE, 0.6)),
    '--color-primary-100': format(tint100),
    '--color-primary-200': format(tint200),
    '--color-primary-300': format(highlight),
    '--color-primary-400': format(link),
    '--color-primary-500': format(base),
    '--color-primary-600': format(mix(base, BLACK, SHADES[600])),
    '--color-primary-700': format(mix(base, BLACK, SHADES[700])),
    '--color-primary-800': format(mix(base, BLACK, SHADES[800])),
    '--color-primary-900': format(mix(base, BLACK, SHADES[900])),
    '--color-primary-950': format(deepest),
  };
}

export { brandPalette, contrastRatio };
