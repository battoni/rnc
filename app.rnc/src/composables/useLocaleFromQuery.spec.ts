import { defineComponent, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import { useLocale } from './useLocale';
import { useLocaleFromQuery } from './useLocaleFromQuery';

/** The instance, where Vue has unwrapped what `setup` returned: `currentLocale` is the string. */
type Host = { currentLocale: string };

const query = ref<Record<string, unknown>>({});

// Partial: `useLocale` reaches the real i18n through the plugins the harness installs.
vi.mock('vue-router', async (importActual) => ({
  ...(await importActual<typeof VueRouter>()),
  useRoute: () => ({
    get query() {
      return query.value;
    },
  }),
}));

const HOST = defineComponent({
  setup() {
    useLocaleFromQuery();

    return { ...useLocale() };
  },
  template: '<div />',
});

describe('useLocaleFromQuery', () => {
  beforeEach(() => {
    localStorage.clear();
    query.value = {};
  });

  // The app mounts after `router.isReady()`, so the route carrying the parameter is already
  // resolved when this runs: a non-immediate watcher would never see the only one that matters.
  it('applies the language the first resolved route carries', () => {
    query.value = { locale: 'en' };

    const host = mountWithPlugins(HOST).vm as unknown as Host;

    expect(host.currentLocale).toBe('en');
  });

  it('ignores a parameter that names no language of this build', () => {
    query.value = { locale: 'tlh' };

    const host = mountWithPlugins(HOST).vm as unknown as Host;
    const before = host.currentLocale;

    expect(before).not.toBe('tlh');
    expect(localStorage.getItem('app.rnc:locale')).toBeNull();
  });
});
