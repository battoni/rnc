import { describe, expect, it } from 'vitest';

/*
 * A form's sticky footer holds Cancel beside Save. At `gap-2` (8px) their grown touch areas
 * OVERLAP, and the later sibling wins the overlap — aiming at Cancel saves. celer-06 asks for
 * `gap-3` at least between adjacent controls (VIS-15, found in seven module forms at once).
 *
 * Scoped to `src/modules`: the shared `MMainDialog` footer is a component, guarded on its own.
 */

const SOURCES = import.meta.glob('/src/modules/**/*.vue', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>;

const STICKY_FOOTER = /class="([^"]*\bsticky\b[^"]*\bbottom-0\b[^"]*\bjustify-end\b[^"]*)"/g;
const TOO_TIGHT = /\bgap-(?:0|0\.5|1|1\.5|2|2\.5)\b/;

function tightFooters(sources: Record<string, string>) {
  return Object.entries(sources).flatMap(([path, source]) =>
    [...source.matchAll(STICKY_FOOTER)]
      .map((match) => match[1] ?? '')
      .filter((classes) => TOO_TIGHT.test(classes))
      .map(() => path)
  );
}

describe('module form footers', () => {
  it('keep their buttons at least gap-3 apart', () => {
    expect(tightFooters(SOURCES)).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    const FOOTER = '<div class="bg-canvas sticky bottom-0 z-10 flex justify-end gap-2 py-4">';

    expect(tightFooters({ 'a.vue': FOOTER })).toEqual(['a.vue']);
    expect(tightFooters({ 'a.vue': FOOTER.replace('gap-2', 'gap-3') })).toEqual([]);
  });
});
