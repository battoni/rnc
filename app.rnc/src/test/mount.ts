// Shared mount helper for component unit tests.
//
// Registers the same plugins the app boots in main.ts (i18n, Pinia, PrimeVue + theme,
// Toast, Confirmation) so components render in a realistic context. Each call gets a
// FRESH Pinia so stores don't leak between tests.
//
// Usage:
//   import { mountWithPlugins } from '@/test/mount';
//   const wrapper = mountWithPlugins(MyComponent, { props: { ... } });

import type { Component } from 'vue';
import { mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import type { ComponentMountingOptions } from '@vue/test-utils';
import i18n from '@Libraries/i18n';
import buildPrimeVueLocale from '@Libraries/primevue/locale';
import ThemePreset from '@Libraries/primevue/theme';

export function mountWithPlugins<TComponent extends Component>(
  component: TComponent,
  options: ComponentMountingOptions<TComponent> = {}
) {
  // Cast to any[] to avoid Vue plugin tuple vs array type mismatch
  const globalPlugins: any[] = [
    i18n,
    createPinia(),
    // The locale too, built from the same i18n the app boots with. PrimeVue keeps its own catalogue,
    // so a harness without it renders month and day names that the app never shows.
    [PrimeVue, { locale: buildPrimeVueLocale(i18n.global.locale.value), theme: { preset: ThemePreset } }],
    ToastService,
    ConfirmationService,
  ];

  return mount(component, {
    ...options,
    global: {
      ...options.global,
      plugins: [...globalPlugins, ...(options.global?.plugins ?? [])],
      directives: {
        // Mirrors the binding onto the element so a spec can assert what a tooltip WOULD say.
        // As a no-op the directive silently dropped its own text: every tooltip in the app had
        // zero coverage, and a spec asserting one could not be written at all.
        tooltip: (element, binding) => element.setAttribute('data-tooltip', String(binding.value ?? '')),
        ...options.global?.directives,
      },
    },
  });
}
