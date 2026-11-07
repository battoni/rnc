import type { UiLocaleOption } from '@Interfaces';
import { UI_LOCALE } from '@Enums';

/** `?locale=pt-BR` on any route. A public site hands a reader off to the app with it. */
const LOCALE_QUERY_PARAM = 'locale';

/** Namespaced like the other preferences (`app.rnc:color-scheme`, `app.rnc:tutor-enabled`). */
const LOCALE_STORAGE_KEY = 'app.rnc:locale';

const UI_LOCALES: UiLocaleOption[] = [
  { label: 'Português', shortLabel: 'PT', value: UI_LOCALE.PT_BR },
  { label: 'English', shortLabel: 'EN', value: UI_LOCALE.EN },
];

export { LOCALE_QUERY_PARAM, LOCALE_STORAGE_KEY, UI_LOCALES };
