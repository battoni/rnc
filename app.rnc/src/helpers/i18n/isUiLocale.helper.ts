import type { UiLocale } from '@Types';
import { UI_LOCALES } from '@Constants';

/**
 * Whether a value names a language this app actually ships.
 *
 * Every entry point for a locale is untrusted — a query string, a storage value written by an older
 * build, a header. Setting one that has no messages leaves the interface showing raw keys, so the
 * answer to an unknown value is always to ignore it rather than to try it.
 */
function isUiLocale(value: unknown): value is UiLocale {
  return UI_LOCALES.some((option) => option.value === value);
}

export { isUiLocale };
