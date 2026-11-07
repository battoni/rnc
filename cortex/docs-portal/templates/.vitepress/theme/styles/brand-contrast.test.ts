import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// The brand shades VitePress reads as TEXT (--vp-c-brand-1/-2) and the label on a brand FILL
// (--vp-button-brand-text) must clear 4.5:1 in light mode. A light brand used as-is measured 1.51:1
// as a link and as white-on-lime. Measured in a browser too; this keeps the numbers from regressing.

const stylesDir = dirname(fileURLToPath(import.meta.url));
const themeDir = resolve(stylesDir, '..');

// The page surfaces text sits on in light mode: bg, bg-soft/alt, inline-code bg.
const LIGHT_SURFACES = ['#ffffff', '#f6f6f7', '#eff0f3'];

type Rgb = [number, number, number];

function hexToRgb(hex: string): Rgb {
  const value = hex.replace('#', '');
  return [0, 2, 4].map((index) => parseInt(value.slice(index, index + 2), 16)) as Rgb;
}

function luminance(rgb: Rgb): number {
  const [red, green, blue] = rgb
    .map((channel) => channel / 255)
    .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));
  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrast(first: Rgb, second: Rgb): number {
  const [light, dark] = [luminance(first), luminance(second)].sort((one, other) => other - one);
  return (light + 0.05) / (dark + 0.05);
}

/** A hex, or `color-mix(in srgb, #hex N%, black|white)` — the only two shapes brand.css uses. */
function resolveColor(value: string): Rgb {
  const mix = value.match(/color-mix\(in srgb,\s*(#[0-9a-f]{6})\s+(\d+)%,\s*(black|white)\)/i);
  if (!mix) return hexToRgb(value.trim());
  const [, hex, percent, other] = mix;
  const weight = Number(percent) / 100;
  const target = other === 'black' ? 0 : 255;
  return hexToRgb(hex).map((channel) => Math.round(channel * weight + target * (1 - weight))) as Rgb;
}

function rootDeclaration(css: string, name: string): string {
  const root = css.match(/:root\s*\{([\s\S]*?)\n\}/)?.[1] ?? '';
  const value = root.match(new RegExp(`${name}:\\s*([^;]+);`))?.[1];
  assert.ok(value, `brand.css :root declares ${name}`);
  return value;
}

test('brand: the light-mode text shades and the label on a brand fill clear 4.5:1', async (context) => {
  const css = await readFile(resolve(stylesDir, 'brand.css'), 'utf8');
  if (css.includes('{{')) {
    context.skip('brand.css still has its placeholders');
    return;
  }

  for (const name of ['--vp-c-brand-1', '--vp-c-brand-2']) {
    const text = resolveColor(rootDeclaration(css, name));
    for (const surface of LIGHT_SURFACES) {
      const ratio = contrast(text, hexToRgb(surface));
      assert.ok(ratio >= 4.5, `${name} on ${surface} is ${ratio.toFixed(2)}:1`);
    }
  }

  const label = resolveColor(rootDeclaration(css, '--vp-button-brand-text'));
  for (const name of ['--vp-button-brand-bg', '--vp-button-brand-hover-bg', '--vp-button-brand-active-bg']) {
    const ratio = contrast(label, resolveColor(rootDeclaration(css, name)));
    assert.ok(ratio >= 4.5, `--vp-button-brand-text on ${name} is ${ratio.toFixed(2)}:1`);
  }
});

test('brand: a hand-made brand fill takes the button label token, never the page background', async () => {
  const files = ['components/ModeToggle.vue', 'components/PdfExportButton.vue', 'styles/pdf-export.css'];

  for (const file of files) {
    const source = await readFile(resolve(themeDir, file), 'utf8');
    for (const [rule] of source.matchAll(/[^{}]*\{[^{}]*background:\s*var\(--vp-(?:c-brand|button-brand)[^{}]*\}/g)) {
      assert.match(rule, /color:\s*var\(--vp-button-brand-(?:hover-|active-)?text\)/, `${file}: ${rule.trim()}`);
    }
  }
});
