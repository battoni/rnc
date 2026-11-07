import { describe, expect, it } from 'vitest';
import VITE_CONFIG from '../../../vite.config.ts?raw';
import VITEST_CONFIG from '../../../vitest.config.ts?raw';

/*
 * The core/local translation pattern only holds while vite.config.ts and vitest.config.ts give
 * VueI18nPlugin the SAME include glob, and that glob stops at the top level.
 *
 * The plugin precompiles every file its glob matches. `src/locales/local/*.json` must reach
 * `index.ts` as plain strings, because the merge over the core happens there, after the plugin
 * has run. vitest.config.ts shipped with `./src/locales/**`, which reached into `local/`: a local
 * message carrying a `{param}` then failed inside vue-i18n's message evaluation the moment it was
 * translated, in every spec that mounts. It never fired only because rnc's local files are
 * `{}` — a project's first parameterised override would have been its first red suite.
 *
 * The configs are read as TEXT through Vite's `?raw`, and both obvious alternatives were tried
 * first. Importing vite.config.ts under jsdom trips an esbuild invariant (see
 * scripts/check-aliases.mjs). Reading it with `node:fs` passed nothing and broke more: under
 * jsdom a URL built from `import.meta.url` resolved to `/vite.config.ts` at the disk root, and
 * this file is type-checked by tsconfig.app.json — the DOM program, with no Node types — so the
 * import alone failed `type-check` with TS2307, which is a CI step.
 */

const PLUGIN_INCLUDE = /VueI18nPlugin\(\{[\s\S]*?include:\s*resolve\(\s*[^,]+,\s*'([^']+)'\s*\)/;

const CONFIGS = { 'vite.config.ts': VITE_CONFIG, 'vitest.config.ts': VITEST_CONFIG };

function includeOf(source: string) {
  return source.match(PLUGIN_INCLUDE)?.[1];
}

describe('VueI18nPlugin include', () => {
  it('is declared in both configs', () => {
    // Anchor first: two missed matches are `undefined === undefined`, which would let the
    // comparison below pass with this check reading nothing at all.
    expect(includeOf(VITE_CONFIG)).toBeTruthy();
    expect(includeOf(VITEST_CONFIG)).toBeTruthy();
  });

  it('is the same glob in the suite as in the build', () => {
    expect(includeOf(VITEST_CONFIG)).toBe(includeOf(VITE_CONFIG));
  });

  it('stops at the top level, so local overrides are never precompiled', () => {
    for (const [configFile, source] of Object.entries(CONFIGS)) {
      expect(includeOf(source), configFile).toBe('./src/locales/*.json');
    }
  });
});
