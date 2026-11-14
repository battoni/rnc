import messages from '@intlify/unplugin-vue-i18n/messages';
import { createI18n } from 'vue-i18n';
import type { MessageTree } from './deepMergeMessages';
import { DEFAULT_LANGUAGE, LOCALE_STORAGE_KEY, UI_LOCALES } from '@Constants';
import localEn from '../../locales/local/en.json';
import localPtBr from '../../locales/local/pt-BR.json';
import deepMergeMessages from './deepMergeMessages';
import { withFirmPlaceholders } from './firmPlaceholders';

// Project-owned translations (src/locales/local/*) deep-merged OVER the synced core, so a
// rnc sync can never revert them. The plugin loads only the top-level core locales
// (vite.config include is scoped to ./src/locales/*.json); the local overrides are merged here.
// Their `{firmShort}` / `{firmName}` become links to the host firm's names (`firmPlaceholders.ts`).
const localMessages: Record<string, MessageTree> = {
  en: withFirmPlaceholders(localEn),
  'pt-BR': withFirmPlaceholders(localPtBr),
};

/**
 * Before the host firm answers. A link that resolves to an empty message still prints its key (vue-i18n's
 * `linked()`), so a first visit with no stored firm can flash `firm.short` — a Known Gap in `locales/RULES.md`.
 */
const NO_FIRM_NAMES = { name: '', short: '' };

const coreMessages = messages as Record<string, MessageTree>;

const mergedMessages = Object.fromEntries(
  Object.entries(coreMessages).map(([locale, coreTree]) => [
    locale,
    deepMergeMessages(deepMergeMessages(coreTree, localMessages[locale] ?? {}), { firm: NO_FIRM_NAMES }),
  ])
);

/*
 * The language someone chose, if they chose one. Validated against what this build ships, because
 * a value written by an older build (or by hand) would leave the interface showing raw keys.
 *
 * Read here rather than through `@Helpers`: that barrel builds the router on import, and this runs
 * while the libraries themselves are being assembled.
 */
function storedLocale(): string | null {
  try {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY);

    return UI_LOCALES.some((option) => option.value === stored) ? stored : null;
  } catch {
    return null;
  }
}

const i18n = createI18n({
  fallbackLocale: 'en',
  fallbackWarn: false,
  legacy: false,
  locale: storedLocale() ?? DEFAULT_LANGUAGE ?? navigator.language ?? 'en',
  messages: mergedMessages,
  missingWarn: false,
});

/*
 * `useLocale` mirrors a SWITCH on `<html lang>`; this mirrors the language the app BOOTS in.
 * index.html cannot know it (a build default, or the choice stored on an earlier visit), and a
 * Portuguese page announced as English is read by a screen reader in the wrong voice.
 */
document.documentElement.lang = i18n.global.locale.value;

/*
 * The host firm's names behind `{firmShort}` and `{firmName}`, in every language the build ships. Called by
 * `useBrandTheme` whenever the firm answers; until then the names are empty — the tab's stored answer
 * usually fills them before the first paint.
 */
function setFirmNames(names: { name: string; short: string }) {
  const FIRM = { name: asLiteralMessage(names.name), short: asLiteralMessage(names.short) };

  Object.keys(mergedMessages).forEach((locale) => i18n.global.mergeLocaleMessage(locale, { firm: FIRM }));
}

/*
 * A firm's name is data typed in the console, not message syntax (rnc-4 verify P1-S1): stored raw, a `|`
 * splits it into plural forms ("Souza | Lima" printed "Souza"), an `@` starts a link and a `{` a
 * parameter. A literal interpolation `{'…'}` prints its content as is — only `'` and `\` are escaped
 * inside it.
 */
function asLiteralMessage(text: string): string {
  return `{'${text.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'}`;
}

export default i18n;
export { setFirmNames };
