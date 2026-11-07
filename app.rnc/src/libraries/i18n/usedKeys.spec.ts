import { describe, expect, it } from 'vitest';
import i18n from './index';

const LOCALES = ['en', 'pt-BR'];

// `t('a.b')` or `$t('a.b')` as a call of its own — not the tail of `split('.')` or of `i18n.global.t`.
const TRANSLATION_CALL = /(?<![\w.$])\$?t\(\s*['"]([a-zA-Z0-9_.]+)['"]/g;

const SOURCES = import.meta.glob(['../../**/*.vue', '../../**/*.ts'], {
  eager: true,
  import: 'default',
  query: '?raw',
});

// HELPERS
function usedKeys(): Array<{ file: string; key: string }> {
  return Object.entries(SOURCES)
    .filter(([file]) => !file.endsWith('.spec.ts') && !file.endsWith('.d.ts') && !file.includes('/test/'))
    .flatMap(([file, content]) =>
      [...(content as string).matchAll(TRANSLATION_CALL)]
        .map((match) => match[1] as string)
        // A key built by concatenation (`t('users.form.' + field)`) ends in a dot and is not a key.
        .filter((key) => key.includes('.') && !key.endsWith('.'))
        .map((key) => ({ file: file.replace('../../', 'src/'), key }))
    );
}

/*
 * A key that does not exist breaks nothing you can see in a test: vue-i18n answers with the key
 * itself, so the screen reads "common.actions.menu" until a person — or a screen reader — says so.
 * This is the net for that (from new.impressao, where a menu button's key sat outside both
 * locales for months).
 */
describe('locales', () => {
  it.each(LOCALES)('%s has every literal key the source asks for', (locale) => {
    const missing = usedKeys().filter(({ key }) => !i18n.global.te(key, locale));

    expect(missing).toEqual([]);
  });
});
