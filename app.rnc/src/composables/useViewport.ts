import { onBeforeUnmount, onMounted, readonly, ref } from 'vue';
import { MOBILE_BREAKPOINT_PX } from '@Constants';

/**
 * Whether the viewport is narrow enough that a layout must change shape rather than shrink.
 *
 * A `matchMedia` listener and not a `resize` one: the browser fires resize on every pixel of a
 * drag, and this only ever answers one question. The query also settles before the first paint,
 * so a component asking on mount gets the real answer instead of the desktop default.
 *
 * It reads the same 768 the Tailwind `md:` prefix does, via `MOBILE_BREAKPOINT_PX` — a screen is
 * mobile here exactly when it is mobile in the classes beside it.
 */
export function useViewport() {
  const isMobile = ref(readMatches());

  let query: MediaQueryList | undefined;

  onMounted(onViewportMount);
  onBeforeUnmount(onViewportUnmount);

  // HELPERS
  /**
   * Guarded because jsdom and older engines reach here without `matchMedia`, and a layout that
   * refuses to mount is worse than one that assumes the wide case.
   */
  function readMatches(): boolean {
    return window.matchMedia?.(`(max-width: ${MOBILE_BREAKPOINT_PX - 1}px)`).matches ?? false;
  }

  // EVENTS
  function onViewportMount() {
    query = window.matchMedia?.(`(max-width: ${MOBILE_BREAKPOINT_PX - 1}px)`);

    isMobile.value = query?.matches ?? false;

    query?.addEventListener('change', onQueryChange);
  }

  function onViewportUnmount() {
    query?.removeEventListener('change', onQueryChange);
  }

  function onQueryChange(event: MediaQueryListEvent) {
    isMobile.value = event.matches;
  }

  return { isMobile: readonly(isMobile) };
}
