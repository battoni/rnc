import { describe, expect, it } from 'vitest';
import * as directives from './index';

const sources = import.meta.glob('/src/**/*.vue', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>;

const toKebab = (name: string) =>
  name
    .slice(1)
    .replace(/([a-z])([A-Z])/g, '$1-$2')
    .toLowerCase();

describe('directive imports', () => {
  it('finds the components and the directives to check', () => {
    expect(Object.keys(sources).length).toBeGreaterThan(20);
    expect(Object.keys(directives).length).toBeGreaterThanOrEqual(4);
  });

  // No directive is registered globally (no app.directive call), so a template that uses one
  // without importing it renders without the effect and Vue only warns.
  for (const name of Object.keys(directives)) {
    it(`every component using v-${toKebab(name)} imports ${name}`, () => {
      const usage = new RegExp(`\\sv-${toKebab(name)}(?![\\w-])`);
      const importing = new RegExp(
        `import\\s*\\{[^}]*\\b${name}\\b[^}]*\\}\\s*from\\s*'(@Directives|[./]+[\\w./]*directives[\\w./]*)'`
      );
      const missing = Object.entries(sources)
        .filter(([, code]) => usage.test(code) && !importing.test(code))
        .map(([path]) => path);
      expect(missing).toEqual([]);
    });
  }
});
