import { watch } from 'vue';
import { useRoute } from 'vue-router';
import { LOCALE_QUERY_PARAM } from '@Constants';
import { useLocale } from './useLocale';

/**
 * `?locale=` on any route switches the language — the handoff a public site uses to keep a reader
 * in the language they were already reading.
 *
 * Called once, from `App.vue`. The watcher is `immediate` because the app mounts after
 * `router.isReady()`: without it the first resolved route — the one carrying the parameter — would
 * be the only one never seen. An unknown value is ignored by `setLocale`.
 */
function useLocaleFromQuery() {
  const route = useRoute();
  const { setLocale } = useLocale();

  watch(() => route.query[LOCALE_QUERY_PARAM], onLocaleQueryChange, { immediate: true });

  // EVENTS
  function onLocaleQueryChange(value: unknown) {
    if (typeof value !== 'string') return;

    setLocale(value);
  }
}

export { useLocaleFromQuery };
