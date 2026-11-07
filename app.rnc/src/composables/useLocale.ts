import { computed } from 'vue';
import { usePrimeVue } from 'primevue/config';
import { useI18n } from 'vue-i18n';
import type { UiLocale } from '@Types';
import { LOCALE_STORAGE_KEY, UI_LOCALES } from '@Constants';
import { isUiLocale } from '@Helpers';
import { applyPrimeVueLocale } from '@Libraries/primevue/locale';

/**
 * The language switch, and the only writer of the global locale.
 *
 * Four things have to happen together or the app disagrees with itself: vue-i18n switches, PrimeVue's
 * own catalogue follows (it never reads vue-i18n, so a DatePicker kept "August / Sun" until a reload),
 * the choice persists (read back by `libraries/i18n` on the next boot), and `<html lang>` follows —
 * that attribute is what a screen reader picks its voice from and what the browser offers to
 * translate, so a page in Portuguese announced as English is a real defect, not a detail.
 */
function useLocale() {
  const { config: primeVueConfig } = usePrimeVue();
  const { locale } = useI18n({ useScope: 'global' });

  const currentLocale = computed(() => locale.value as UiLocale);

  // HELPERS
  function persistLocale(value: UiLocale) {
    try {
      localStorage.setItem(LOCALE_STORAGE_KEY, value);
    } catch {
      // Private mode or blocked storage: the switch still applies to this session.
    }
  }

  // EVENTS
  function setLocale(value: unknown): boolean {
    if (!isUiLocale(value)) return false;

    locale.value = value;
    applyPrimeVueLocale(primeVueConfig.locale ?? {}, value);
    persistLocale(value);
    document.documentElement.lang = value;

    return true;
  }

  return { availableLocales: UI_LOCALES, currentLocale, setLocale };
}

export { useLocale };
