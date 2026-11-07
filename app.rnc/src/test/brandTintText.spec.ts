import { describe, expect, it } from 'vitest';

/*
 * A pale brand tint takes the ramp's darkest step as its word or icon (theme RULES, "A pale tint
 * takes ink, not the contrast token"). `primary-700` on `primary-100` measured 1.83:1 in
 * `battoni-dev` — under the 4.5:1 of a word and the 3:1 of an icon — and it was written in the Tag
 * helper, an icon tile, a list's icon bubble and an idea status chip. Neither side flips with the
 * scheme, so the pair is wrong in both. Every class string in the app is read here.
 */

const SOURCES = {
  ...import.meta.glob('/src/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob(['/src/**/*.ts', '!/src/**/*.spec.ts'], { query: '?raw', import: 'default', eager: true }),
} as Record<string, string>;

const CLASS_STRING = /["'`]([^"'`\n]*\bbg-primary-(?:50|100|200)\b[^"'`\n]*)["'`]/g;
const WEAK_BRAND_WORD = /\btext-primary-(?!950\b|contrast\b)\d+\b/;

function weakPairs(sources: Record<string, string>) {
  return Object.entries(sources).flatMap(([path, source]) =>
    [...source.matchAll(CLASS_STRING)]
      .map((match) => match[1] ?? '')
      .filter((classes) => WEAK_BRAND_WORD.test(classes))
      .map((classes) => `${path}: ${classes}`)
  );
}

describe('a pale brand tint', () => {
  it('never carries a lighter step of the ramp as its word or icon', () => {
    expect(weakPairs(SOURCES)).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    expect(weakPairs({ 'a.ts': "primary: 'bg-primary-100 text-primary-700'," })).toHaveLength(1);
    expect(weakPairs({ 'a.vue': '<span class="bg-primary-100 text-primary-950" />' })).toEqual([]);
    expect(weakPairs({ 'a.vue': '<span class="bg-primary-500 text-primary-contrast" />' })).toEqual([]);
  });
});
