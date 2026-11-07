import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it } from 'vitest';
import type { UiLocaleOption } from '@Interfaces';
import { mountWithPlugins } from '@/test/mount';
import { LOCALE_STORAGE_KEY } from '@Constants';
import { useLocale } from './useLocale';

const HOST = defineComponent({
  setup() {
    return { ...useLocale() };
  },
  template: '<div />',
});

/*
 * Read through the component instance, where Vue has already unwrapped the refs `setup` returned —
 * `vm.currentLocale` is the string, never a ref. Typing it as the composable's own return value
 * (which carries refs) is what made the first version of these tests read `undefined`.
 */
type Host = { availableLocales: UiLocaleOption[]; currentLocale: string; setLocale: (value: unknown) => boolean };

function mountHost(): Host {
  return mountWithPlugins(HOST).vm as unknown as Host;
}

describe('useLocale', () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.lang = '';
  });

  it('switches the app, persists the choice and follows it on <html lang>', () => {
    const host = mountHost();

    expect(host.setLocale('pt-BR')).toBe(true);
    expect(host.currentLocale).toBe('pt-BR');
    expect(localStorage.getItem(LOCALE_STORAGE_KEY)).toBe('pt-BR');
    expect(document.documentElement.lang).toBe('pt-BR');
  });

  // An unknown value has no messages behind it: applying it would show raw keys on every screen.
  // PrimeVue keeps its own catalogue: a switch that left it alone put "August / Sun" back in a pt-BR app.
  it('switches the PrimeVue catalogue with the app', () => {
    const wrapper = mountWithPlugins(HOST);
    const host = wrapper.vm as unknown as Host;
    const { locale } = wrapper.vm.$primevue.config;

    host.setLocale('en');
    expect(locale?.monthNames?.[7]).toBe('August');

    host.setLocale('pt-BR');
    expect(locale?.monthNames?.[7]).toBe('Agosto');
    expect(locale?.dayNamesMin?.[0]).toBe('Dom');
    expect(locale?.aria?.close).toBe('Fechar');

    host.setLocale('en');
    expect(locale?.monthNames?.[7]).toBe('August');
    expect(locale?.aria?.close).toBe('Close');
  });

  it('ignores a language this build does not ship', () => {
    const host = mountHost();
    const before = host.currentLocale;

    expect(host.setLocale('tlh')).toBe(false);
    expect(host.setLocale(undefined)).toBe(false);
    expect(host.currentLocale).toBe(before);
    expect(localStorage.getItem(LOCALE_STORAGE_KEY)).toBeNull();
  });

  it('offers every language the app ships, by its own name', () => {
    const { availableLocales } = mountHost();

    expect(availableLocales.map((option) => option.value)).toEqual(['pt-BR', 'en']);
    expect(availableLocales.map((option) => option.label)).toEqual(['Português', 'English']);
  });
});
