import { describe, expect, it } from 'vitest';
import i18n from '@/libraries/i18n';
import { TIP_ANCHORS } from './tipAnchor.constant';

/*
 * A tip anchor is a string in two unrelated places: an `<ATip tip-key="...">` in a template and a
 * row in `TIP_ANCHORS`. Nothing at runtime complains when they stop matching — the atom simply
 * renders nothing, and a tip that took someone an hour to write silently stops appearing.
 * This is the check that makes that a failing test instead of a quiet regression.
 */
/*
 * Only LITERAL keys. The negative lookbehind on `:` is what keeps `:tipKey="tab.tipKey"` — a
 * binding that forwards someone else's key — from being read as an anchor named "tab.tipKey".
 */
const STATIC_TIP_KEY = /(?<![:\w-])tip-key="([^"]+)"/g;
const TAB_PANEL_TIP_KEY = /(?<![:\w-])tipKey="([^"]+)"/g;

/*
 * Anchors built at runtime cannot be found by reading templates. Each prefix here is a promise that
 * some component composes keys under it. The bootstrap composes none — add a prefix ONLY alongside
 * the component that composes it, or this list becomes a place to hide a broken anchor.
 */
const DYNAMIC_PREFIXES: string[] = [];

const LOCALES = ['pt-BR', 'en'];
const ROUTE_TABLE_IMPORT_TIMEOUT_MS = 30_000;

const vueSources = import.meta.glob('../**/*.vue', { query: '?raw', import: 'default', eager: true });

// HELPERS
function anchorKeys(): string[] {
  return TIP_ANCHORS.map((anchor) => anchor.key);
}

function usedAnchorKeys(): Array<{ file: string; key: string }> {
  return Object.entries(vueSources).flatMap(([file, content]) =>
    [...(content as string).matchAll(STATIC_TIP_KEY), ...(content as string).matchAll(TAB_PANEL_TIP_KEY)]
      .map((match) => match[1] as string)
      .filter((key) => !key.includes('${'))
      .map((key) => ({ file, key }))
  );
}

describe('TIP_ANCHORS', () => {
  it('declares every anchor a template actually renders', () => {
    const declared = anchorKeys();
    const undeclared = usedAnchorKeys().filter(({ key }) => !declared.includes(key));

    expect(undeclared).toEqual([]);
  });

  it('has no anchor that nothing renders', () => {
    const used = usedAnchorKeys().map(({ key }) => key);
    const orphans = anchorKeys().filter(
      (key) => !used.includes(key) && !DYNAMIC_PREFIXES.some((prefix) => key.startsWith(prefix))
    );

    expect(orphans).toEqual([]);
  });

  it('has unique keys', () => {
    const keys = anchorKeys();

    expect(keys).toHaveLength(new Set(keys).size);
  });

  it.each(LOCALES)('resolves every label and section in %s', (locale) => {
    const missing = TIP_ANCHORS.flatMap((anchor) =>
      [anchor.labelKey, anchor.sectionKey].filter((key) => !i18n.global.te(key, locale))
    );

    expect(missing).toEqual([]);
  });

  it('gives every anchor a way to reach the screen', () => {
    // A row in the Dicas view with no link leaves the editor guessing what their words sit next to.
    const unreachable = TIP_ANCHORS.filter((anchor) => !anchor.routeName);

    expect(unreachable.map((anchor) => anchor.key)).toEqual([]);
  });

  /*
   * The explicit timeout is load-bearing: importing the route table transforms every view, which
   * takes ~3.5s alone and crossed the default 5s when the suite ran in parallel — a red that said
   * nothing about the anchors.
   */
  it(
    'points every declared route at a real route name',
    async () => {
      // Relative, not `@Libraries/...`: the test config stubs the router alias.
      const routes = (await import('../libraries/router/routes')).default;
      const names = routes.map((route) => String(route.name ?? ''));

      // Routes are registered per locale (`tips-en`, `tips-pt-BR`); the anchor stores the stem.
      const broken = TIP_ANCHORS.filter(
        (anchor) =>
          anchor.routeName &&
          !names.some((name) => name.startsWith(`${anchor.routeName}-`) || name === anchor.routeName)
      );

      expect(broken.map((anchor) => anchor.key)).toEqual([]);
    },
    ROUTE_TABLE_IMPORT_TIMEOUT_MS
  );
});
