import { describe, expect, it } from 'vitest';

/*
 * A form in `MMainDialog` ends with a gap filler: `absolute bottom-0 w-full`, pinned to the sheet.
 * Without a horizontal inset it sits at its static position — the content's padding in — and a
 * full width from there ends that far past the sheet. On a phone the page became 410px wide, the
 * browser zoomed out to fit it, and the dialog covered 370 of 390px (`e2e/theme/dialog-mask.spec.ts`
 * measures it on the user form). Every filler in the app is read here.
 */

const SOURCES = import.meta.glob('/src/**/*.vue', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>;

const ABSOLUTE_FULL_WIDTH = /class="([^"]*\babsolute\b[^"]*)"/g;
const HORIZONTAL_ANCHOR = /\b(?:left-0|inset-x-0|inset-0|start-0)\b/;

function unanchoredFillers(sources: Record<string, string>) {
  return Object.entries(sources).flatMap(([path, source]) =>
    [...source.matchAll(ABSOLUTE_FULL_WIDTH)]
      .map((match) => match[1] ?? '')
      .filter((classes) => /\bbottom-0\b/.test(classes) && /\bw-full\b/.test(classes))
      .filter((classes) => !HORIZONTAL_ANCHOR.test(classes))
      .map((classes) => `${path}: ${classes}`)
  );
}

describe('dialog gap fillers', () => {
  it('are anchored to the left edge of what they fill', () => {
    expect(unanchoredFillers(SOURCES)).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    const FILLER = '<div class="bg-canvas absolute bottom-0 z-10 h-5 w-full" />';

    expect(unanchoredFillers({ 'a.vue': FILLER })).toHaveLength(1);
    expect(unanchoredFillers({ 'a.vue': FILLER.replace('bottom-0', 'bottom-0 left-0') })).toEqual([]);
  });
});
