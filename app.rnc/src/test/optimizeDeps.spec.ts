import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import { describe, expect, it } from 'vitest';
import VITE_CONFIG from '../../vite.config.ts?raw';

/*
 * Every PrimeVue component a template renders has to be pre-bundled in `optimizeDeps.include`.
 *
 * The components arrive through the auto-import resolver, so Vite's startup scan never sees them.
 * The first hard load of a route that renders one makes Vite re-optimize mid-navigation: two copies
 * of Vue exist for a moment, provide/inject breaks, the route guard throws, and the page is blank
 * until a manual reload. Dev only — production is fine — which is why nobody catches it by hand.
 *
 * The tags are resolved with the SAME resolver `vite.config.ts` uses, so "is this PrimeVue, and
 * which module" is answered the way the build answers it. The config is read as TEXT (see
 * `libraries/i18n/localOverrides.spec.ts` for why it is not imported).
 */

const TEMPLATES = import.meta.glob<string>('/src/**/*.vue', { eager: true, import: 'default', query: '?raw' });

const [COMPONENT_RESOLVER] = PrimeVueResolver();

function primeVueModuleOf(tag: string): string | undefined {
  const IS_COMPONENT_RESOLVER = typeof COMPONENT_RESOLVER === 'object' && COMPONENT_RESOLVER.type === 'component';
  const RESOLVED = IS_COMPONENT_RESOLVER ? COMPONENT_RESOLVER.resolve(tag) : undefined;

  return typeof RESOLVED === 'object' && RESOLVED && 'from' in RESOLVED ? RESOLVED.from : undefined;
}

function renderedPrimeVueModules(): string[] {
  const FOUND = new Set<string>();

  Object.values(TEMPLATES).forEach((source) => {
    const TEMPLATE = source.slice(source.indexOf('<template'));

    [...TEMPLATE.matchAll(/<([A-Z][A-Za-z]+)[\s/>]/g)].forEach(([, tag]) => {
      const MODULE = tag ? primeVueModuleOf(tag) : undefined;

      if (MODULE) FOUND.add(MODULE);
    });
  });

  return [...FOUND].sort();
}

describe('vite optimizeDeps', () => {
  it('pre-bundles every PrimeVue component a template renders', () => {
    const RENDERED = renderedPrimeVueModules();

    // Anchor first: an empty list would pass the check below without checking anything.
    expect(RENDERED).toContain('primevue/button');

    expect(RENDERED.filter((module) => !VITE_CONFIG.includes(`'${module}'`))).toEqual([]);
  });
});
