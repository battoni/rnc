import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * Three PrimeVue controls whose words and icons the preset colours, measured in both schemes.
 *
 * `contrast.spec.ts` covers the text tiers, the filled buttons, the brand words and what renders on
 * the showcase. These three were left with Aura's defaults and read under their floors:
 *
 * - the unselected option of a SelectButton / ToggleButton: `surface.500` on `surface.100`, 2.63:1
 *   in light (/news "Unread", /components "Tasks");
 * - a text button with `severity="secondary"`: the same `surface.500`, 2.63:1 on panel-muted in
 *   light (the comment bin on /ideas/1), and in dark `surface.400` on its own pressed ground
 *   (`surface.700`), 2.58:1;
 * - the default `rounded` Tag, painted from `tag.primary.*` (the preset set `tag.root.*`, which no rule reads):
 *   `primary.600` on `primary.100`, 1.83:1 in light.
 *
 * A word is 4.5:1. The icon of a toggle option is a graphic, 3:1.
 */

const DESIGN_USER = { ...mockUser, permissions: [...mockUser.permissions, 'design.read'] };

type Pair = { label: string; colour: string; ground: string; floor: number };

const TEXT = 4.5;
const ICON = 3;

const PAIRS: [string, string, number][] = [
  ['--p-togglebutton-color', '--p-togglebutton-background', TEXT],
  ['--p-togglebutton-hover-color', '--p-togglebutton-hover-background', TEXT],
  ['--p-togglebutton-checked-color', '--p-togglebutton-content-checked-background', TEXT],
  ['--p-togglebutton-icon-color', '--p-togglebutton-background', ICON],
  ['--p-togglebutton-icon-hover-color', '--p-togglebutton-hover-background', ICON],
  ['--p-button-text-secondary-color', '--color-canvas', TEXT],
  ['--p-button-text-secondary-color', '--color-panel', TEXT],
  ['--p-button-text-secondary-color', '--color-panel-muted', TEXT],
  ['--p-button-text-secondary-color', '--p-button-text-secondary-hover-background', TEXT],
  ['--p-button-text-secondary-color', '--p-button-text-secondary-active-background', TEXT],
  // A `rounded` default Tag: `tag.css` matches only an empty `data-p`, so this pair is what renders.
  ['--p-tag-primary-color', '--p-tag-primary-background', TEXT],
];

function luminance([red, green, blue]: number[]) {
  const channels = [red, green, blue].map((value) => {
    const srgb = value! / 255;

    return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * channels[0]! + 0.7152 * channels[1]! + 0.0722 * channels[2]!;
}

function contrastRatio(first: number[], second: number[]) {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);

  return (lighter! + 0.05) / (darker! + 0.05);
}

/** Resolves each pair on probes, with a translucent ground laid over the canvas it sits on. */
async function resolve(page: Page, scheme: string): Promise<Pair[]> {
  return page.evaluate(
    ({ name, pairs }) => {
      document.documentElement.setAttribute('data-theme', name);

      const canvas = document.createElement('div');
      canvas.style.cssText = 'position:fixed;inset:0;background:var(--color-canvas)';
      const ground = document.createElement('div');
      const word = document.createElement('span');
      canvas.appendChild(ground);
      ground.appendChild(word);
      document.body.appendChild(canvas);

      // A 1×1 canvas composites whatever colour syntax the browser resolved (rgb, rgba, color()).
      const paint = document.createElement('canvas').getContext('2d', { willReadFrequently: true })!;

      function flatten(colour: string, under: string) {
        paint.clearRect(0, 0, 1, 1);
        paint.fillStyle = under;
        paint.fillRect(0, 0, 1, 1);
        paint.fillStyle = colour;
        paint.fillRect(0, 0, 1, 1);

        return [...paint.getImageData(0, 0, 1, 1).data.slice(0, 3)].join(',');
      }

      const canvasColour = window.getComputedStyle(canvas).backgroundColor;

      const resolved = pairs.map(([colour, background, floor]) => {
        ground.style.backgroundColor = `var(${background})`;
        word.style.color = `var(${colour})`;

        const groundColour = flatten(window.getComputedStyle(ground).backgroundColor, canvasColour);

        return {
          label: `${colour} on ${background}`,
          colour: flatten(window.getComputedStyle(word).color, `rgb(${groundColour})`),
          ground: groundColour,
          floor,
        };
      });

      canvas.remove();

      return resolved;
    },
    { name: scheme, pairs: PAIRS }
  );
}

for (const scheme of ['light', 'dark'] as const) {
  test(`toggle options, secondary text buttons and the default tag read, ${scheme}`, async ({ page }) => {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: DESIGN_USER }) })
    );
    // The showcase renders all three, so PrimeVue has injected their variables.
    await page.goto('/components');
    await expect(page.locator('[data-pc-name="selectbutton"]').first()).toBeAttached();
    await expect(page.locator('[data-pc-name="tag"]').first()).toBeAttached();

    const pairs = await resolve(page, scheme);

    expect(pairs).toHaveLength(PAIRS.length);

    for (const pair of pairs) {
      const ratio = contrastRatio(pair.colour.split(',').map(Number), pair.ground.split(',').map(Number));

      expect.soft(ratio, `${pair.label} (${scheme}): ${ratio.toFixed(2)}:1`).toBeGreaterThanOrEqual(pair.floor);
    }
  });
}
