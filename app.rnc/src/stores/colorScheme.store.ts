import { defineStore } from 'pinia';
import { COLOR_SCHEME_STORAGE_KEY, DEFAULT_COLOR_SCHEME } from '@Constants';
import { COLOR_SCHEME } from '@Enums';

interface ColorSchemeState {
  preference: COLOR_SCHEME;
  systemPrefersDark: boolean;
}

// Matched once and shared (mirrors the ui.store useWindowSize pattern).
const systemDarkQuery = window.matchMedia('(prefers-color-scheme: dark)');

function readStoredPreference(): COLOR_SCHEME {
  const stored = localStorage.getItem(COLOR_SCHEME_STORAGE_KEY);

  const isValid = stored === COLOR_SCHEME.LIGHT || stored === COLOR_SCHEME.DARK || stored === COLOR_SCHEME.SYSTEM;

  return isValid ? (stored as COLOR_SCHEME) : DEFAULT_COLOR_SCHEME;
}

export default defineStore('colorScheme', {
  state: (): ColorSchemeState => ({
    preference: readStoredPreference(),
    systemPrefersDark: systemDarkQuery.matches,
  }),
  getters: {
    isDark(state): boolean {
      if (state.preference === COLOR_SCHEME.SYSTEM) return state.systemPrefersDark;

      return state.preference === COLOR_SCHEME.DARK;
    },
    resolvedScheme(): COLOR_SCHEME.DARK | COLOR_SCHEME.LIGHT {
      return this.isDark ? COLOR_SCHEME.DARK : COLOR_SCHEME.LIGHT;
    },
  },
  actions: {
    apply() {
      document.documentElement.dataset.theme = this.resolvedScheme;
    },
    init() {
      systemDarkQuery.addEventListener('change', (event) => this.onSystemChange(event));

      this.apply();
    },
    onSystemChange(event: MediaQueryListEvent) {
      this.systemPrefersDark = event.matches;

      this.apply();
    },
    setPreference(preference: COLOR_SCHEME) {
      this.preference = preference;

      localStorage.setItem(COLOR_SCHEME_STORAGE_KEY, preference);

      this.apply();
    },
    toggle() {
      this.setPreference(this.isDark ? COLOR_SCHEME.LIGHT : COLOR_SCHEME.DARK);
    },
  },
});
