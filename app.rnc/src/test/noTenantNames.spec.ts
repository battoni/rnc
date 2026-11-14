import { describe, expect, it } from 'vitest';

/*
 * A15 (rnc-4 R15, D14): the platform names no tenant. Franco is one firm among many, so its name, its
 * manager's and the partner network's founder never appear in a locale, a component or a module — a
 * string that needs the firm writes `{firmShort}` (or `{firmName}`), filled at runtime from the host firm
 * (`libraries/i18n`, `useBrandTheme`). "RNC" belongs to the Channel module alone: in a source file only
 * under `modules/Channel/`, in a locale only under a `channel` key.
 *
 * Extends `noPartnerNetworkNames.spec.ts` (rnc-1 R3) to the tenant's names. Comments are left out (a
 * RULES-style note may tell the history); specs and `test/` folders are left out (they assert it, and a
 * fixture may be Franco).
 */

type LocaleTree = { [key: string]: LocaleTree | string };

const LOCALES = import.meta.glob('/src/locales/**/*.json', { import: 'default', eager: true }) as Record<
  string,
  LocaleTree
>;

const SOURCES = Object.fromEntries(
  Object.entries({
    ...import.meta.glob('/src/**/*.vue', { query: '?raw', import: 'default', eager: true }),
    ...import.meta.glob(['/src/**/*.ts', '!/src/**/*.spec.ts'], { query: '?raw', import: 'default', eager: true }),
  } as Record<string, string>).filter(([path]) => !path.includes('/test/'))
);

const TENANT_NAMES = /\b(?:franco|reinaldo|willy|apw)\b/i;
const NETWORK_ACRONYM = /\bRNC\b/;
const CHANNEL_KEY = 'channel';

const COMMENTS = [/<!--[\s\S]*?-->/g, /\/\*[\s\S]*?\*\//g, /(^|[^:'"`])\/\/.*$/gm];

function withoutComments(source: string): string {
  return COMMENTS.reduce((text, comment) => text.replace(comment, '$1'), source);
}

function sourceNamings(sources: Record<string, string>, banned: RegExp): string[] {
  return Object.entries(sources).flatMap(([path, source]) =>
    withoutComments(source)
      .split('\n')
      .filter((line) => banned.test(line))
      .map((line) => `${path}: ${line.trim()}`)
  );
}

/** Every leaf of a locale tree, with the path of keys that leads to it. */
function leaves(tree: LocaleTree, path: string[] = []): { path: string[]; text: string }[] {
  return Object.entries(tree).flatMap(([key, value]) =>
    typeof value === 'string' ? [{ path: [...path, key], text: value }] : leaves(value, [...path, key])
  );
}

function localeNamings(locales: Record<string, LocaleTree>): string[] {
  return Object.entries(locales).flatMap(([file, tree]) =>
    leaves(tree)
      .filter(
        ({ path, text }) => TENANT_NAMES.test(text) || (NETWORK_ACRONYM.test(text) && !path.includes(CHANNEL_KEY))
      )
      .map(({ path, text }) => `${file} ${path.join('.')}: ${text}`)
  );
}

describe('the platform names no tenant (rnc-4 R15, A15)', () => {
  it('in a locale file', () => {
    expect(Object.keys(LOCALES).length).toBeGreaterThan(1);
    expect(localeNamings(LOCALES)).toEqual([]);
  });

  it('in the text of a component or a module', () => {
    expect(Object.keys(SOURCES).length).toBeGreaterThan(0);
    expect(sourceNamings(SOURCES, TENANT_NAMES)).toEqual([]);
  });

  it('says "RNC" only inside the Channel module', () => {
    const outsideChannel = Object.fromEntries(
      Object.entries(SOURCES).filter(([path]) => !path.startsWith('/src/modules/Channel/'))
    );

    expect(Object.keys(outsideChannel).length).toBeLessThan(Object.keys(SOURCES).length);
    expect(sourceNamings(outsideChannel, NETWORK_ACRONYM)).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    expect(localeNamings({ 'a.json': { site: { proofLabel: 'Por que a Franco' } } })).toHaveLength(1);
    expect(localeNamings({ 'a.json': { navigation: { management: 'Reinaldo' } } })).toHaveLength(1);
    expect(localeNamings({ 'a.json': { site: { proof: { network: 'RNC' } } } })).toHaveLength(1);
    expect(localeNamings({ 'a.json': { navigation: { channel: 'Canal RNC' } } })).toEqual([]);
    expect(localeNamings({ 'a.json': { site: { proofLabel: 'Por que a {firmShort}' } } })).toEqual([]);
    expect(sourceNamings({ 'a.ts': "const PRODUCT_NAME = 'Franco Contabilidade';" }, TENANT_NAMES)).toHaveLength(1);
    expect(sourceNamings({ 'a.vue': '<!-- Franco -->\n<p>{{ name }}</p>' }, TENANT_NAMES)).toEqual([]);
    expect(sourceNamings({ 'a.ts': "/* Reinaldo's */ const a = 1; // Franco" }, TENANT_NAMES)).toEqual([]);
    expect(sourceNamings({ 'a.ts': "const figure = 'RNC';" }, NETWORK_ACRONYM)).toHaveLength(1);
  });
});
