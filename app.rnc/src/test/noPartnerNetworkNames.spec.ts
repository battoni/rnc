import { describe, expect, it } from 'vitest';

/*
 * The partner network and its founder are never named in the product (rnc-1 R3, `ALogo/RULES.md`):
 * the hub is Franco's, and "Willy" or "APW" on a screen would tell a visitor whose network it is.
 * The public layout and the site pages check what they render; this reads every locale file and
 * every `.vue` and `.ts` source, so a string that only a back-office screen shows is caught too.
 * Comments are left out (a RULES-style note may name the ban); specs are left out (they assert it).
 */

const LOCALES = import.meta.glob('/src/locales/**/*.json', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>;

const SOURCES = {
  ...import.meta.glob('/src/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob(['/src/**/*.ts', '!/src/**/*.spec.ts'], { query: '?raw', import: 'default', eager: true }),
} as Record<string, string>;

const BANNED = /\b(?:willy|apw)\b/i;

const COMMENTS = [/<!--[\s\S]*?-->/g, /\/\*[\s\S]*?\*\//g, /(^|[^:'"`])\/\/.*$/gm];

function withoutComments(source: string): string {
  return COMMENTS.reduce((text, comment) => text.replace(comment, '$1'), source);
}

function namings(sources: Record<string, string>, stripComments: boolean): string[] {
  return Object.entries(sources).flatMap(([path, source]) =>
    (stripComments ? withoutComments(source) : source)
      .split('\n')
      .filter((line) => BANNED.test(line))
      .map((line) => `${path}: ${line.trim()}`)
  );
}

describe('the partner network is never named', () => {
  it('in a locale file', () => {
    expect(Object.keys(LOCALES).length).toBeGreaterThan(0);
    expect(namings(LOCALES, false)).toEqual([]);
  });

  // Each phase that adds a module names it here (rnc-2 P11): the glob is what reads it, and this
  // fails if a module ever moved outside it.
  it('reads every module of the hub, the partner portal included', () => {
    const MODULES = ['Diagnostic', 'Home', 'Metrics', 'Partner', 'Pipeline', 'Portal', 'Repricing', 'Site'];

    MODULES.forEach((module) =>
      expect(Object.keys(SOURCES).some((path) => path.startsWith(`/src/modules/${module}/`))).toBe(true)
    );
  });

  it('in the text of a component or a module', () => {
    expect(Object.keys(SOURCES).length).toBeGreaterThan(0);
    expect(namings(SOURCES, true)).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    expect(namings({ 'a.json': '{ "footer": "Rede do Willy" }' }, false)).toHaveLength(1);
    expect(namings({ 'a.vue': '<p>Parceiro APW</p>' }, true)).toHaveLength(1);
    expect(namings({ 'a.ts': "const label = 'apw';" }, true)).toHaveLength(1);
    expect(namings({ 'a.vue': '<!-- never write Willy here -->\n<p>Franco</p>' }, true)).toEqual([]);
    expect(namings({ 'a.ts': '/* not APW */ const a = 1; // nor Willy' }, true)).toEqual([]);
    expect(namings({ 'a.json': '{ "x": "Willyam", "y": "APWX" }' }, false)).toEqual([]);
    expect(namings({ 'a.ts': "const url = 'https://example.com/willy';" }, true)).toHaveLength(1);
  });
});
