import { describe, expect, it } from 'vitest';

/*
 * A transition duration is a motion token (`motion-instant`, `motion-quick`, `motion-calm`,
 * `motion-settle`), never a literal `duration-300`: the tokens are what
 * `prefers-reduced-motion` shortens (`styles/tailwind.css`), and a literal keeps its full length
 * for the person who asked for less. Every template in the app is scanned: components, layouts,
 * modules, views and `App.vue` (the first version read only the shared units).
 */

const SOURCES = {
  ...import.meta.glob('/src/components/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/layouts/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/modules/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/views/**/*.vue', { query: '?raw', import: 'default', eager: true }),
  ...import.meta.glob('/src/App.vue', { query: '?raw', import: 'default', eager: true }),
} as Record<string, string>;

const LITERAL_DURATION = /(?<![\w-])duration-(?:\d+|\[[^\]]+\])(?![\w-])/g;

describe('motion durations are tokens', () => {
  it('no template in the app writes a literal duration class', () => {
    const offenders = Object.entries(SOURCES).flatMap(([path, source]) =>
      [...source.matchAll(LITERAL_DURATION)].map((match) => `${path}: ${match[0]}`)
    );

    expect(offenders).toEqual([]);
  });

  it('bites on the shape it guards', () => {
    expect('transition-all duration-300 active:scale-95'.match(LITERAL_DURATION)).toEqual(['duration-300']);
    expect('duration-[250ms]'.match(LITERAL_DURATION)).toEqual(['duration-[250ms]']);
    expect('motion-calm transition-[grid-template-rows] --motion-duration-x'.match(LITERAL_DURATION)).toBeNull();
  });
});
