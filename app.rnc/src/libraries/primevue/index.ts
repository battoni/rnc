import PrimeVue from 'primevue/config';
import i18n from '../i18n';
import buildPrimeVueLocale from './locale';
import themePreset from './theme';

const options = {
  // PrimeVue never reads vue-i18n, so the DatePicker wrote its month and day names in English inside
  // a pt-BR app. This is the language the app BOOTS in; a switch at runtime goes through
  // `useLocale().setLocale`, which applies the new catalogue to this same config.
  locale: buildPrimeVueLocale(i18n.global.locale.value),
  theme: {
    preset: themePreset,
    options: {
      prefix: 'p',
      darkModeSelector: '[data-theme="dark"]',
      cssLayer: {
        name: 'primevue',
        order: 'theme, base, primevue',
      },
    },
  },
};

const PrimeVuePlugin = {
  package: PrimeVue,
  options,
};

export default PrimeVuePlugin;
