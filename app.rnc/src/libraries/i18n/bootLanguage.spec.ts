import { beforeEach, describe, expect, it, vi } from 'vitest';
import { LOCALE_STORAGE_KEY } from '@Constants';

/*
 * `<html lang>` is what a screen reader picks its voice from. `useLocale` mirrors it on a SWITCH,
 * but index.html ships `lang="en"` and the app boots in pt-BR (or in whatever the person chose last
 * time): without this, every reload announced a Portuguese page as English until someone switched.
 */
async function bootI18n() {
  vi.resetModules();

  return (await import('./index')).default;
}

describe('libraries/i18n boot', () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.lang = '';
  });

  it('mirrors the language the app boots in on <html lang>', async () => {
    const i18n = await bootI18n();

    expect(i18n.global.locale.value).not.toBe('');
    expect(document.documentElement.lang).toBe(i18n.global.locale.value);
  });

  it('mirrors the language chosen on an earlier visit', async () => {
    localStorage.setItem(LOCALE_STORAGE_KEY, 'en');
    document.documentElement.lang = 'pt-BR';

    await bootI18n();

    expect(document.documentElement.lang).toBe('en');
  });
});
