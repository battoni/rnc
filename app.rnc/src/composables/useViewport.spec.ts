import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MOBILE_BREAKPOINT_PX } from '@Constants';
import { useViewport } from './useViewport';

let listeners: Array<(event: MediaQueryListEvent) => void> = [];

function stubMatchMedia(matches: boolean) {
  listeners = [];

  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string) => ({
      matches,
      media: query,
      addEventListener: (_name: string, listener: (event: MediaQueryListEvent) => void) => listeners.push(listener),
      removeEventListener: (_name: string, listener: (event: MediaQueryListEvent) => void) => {
        listeners = listeners.filter((candidate) => candidate !== listener);
      },
      addListener: () => {},
      removeListener: () => {},
      dispatchEvent: () => false,
      onchange: null,
    }),
  });
}

function mountViewport() {
  let viewport!: ReturnType<typeof useViewport>;

  const wrapper = mount(
    defineComponent({
      setup() {
        viewport = useViewport();

        return () => h('div');
      },
    })
  );

  return { viewport, wrapper };
}

describe('useViewport', () => {
  beforeEach(() => {
    stubMatchMedia(false);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('asks for the same 768 the md: prefix uses', () => {
    const queries: string[] = [];

    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => {
        queries.push(query);

        return { matches: false, media: query, addEventListener: () => {}, removeEventListener: () => {} };
      },
    });

    mountViewport();

    expect(queries[0]).toBe(`(max-width: ${MOBILE_BREAKPOINT_PX - 1}px)`);
  });

  it('reports a wide viewport as not mobile', () => {
    expect(mountViewport().viewport.isMobile.value).toBe(false);
  });

  it('reports a narrow viewport as mobile', () => {
    stubMatchMedia(true);

    expect(mountViewport().viewport.isMobile.value).toBe(true);
  });

  // A component asking on mount must get the real answer, not the wide default it was seeded with.
  it('answers before the first paint rather than after a resize', () => {
    stubMatchMedia(true);

    const { viewport } = mountViewport();

    expect(viewport.isMobile.value).toBe(true);
    expect(listeners).toHaveLength(1);
  });

  it('follows the viewport across the breakpoint', async () => {
    const { viewport } = mountViewport();

    listeners.forEach((listener) => listener({ matches: true } as MediaQueryListEvent));

    expect(viewport.isMobile.value).toBe(true);

    listeners.forEach((listener) => listener({ matches: false } as MediaQueryListEvent));

    expect(viewport.isMobile.value).toBe(false);
  });

  it('lets go of the query when the component goes', () => {
    const { wrapper } = mountViewport();

    wrapper.unmount();

    expect(listeners).toHaveLength(0);
  });

  // jsdom and older engines reach here without matchMedia; a layout that refuses to mount is
  // worse than one that assumes the wide case.
  it('assumes the wide case when the engine has no matchMedia', () => {
    Object.defineProperty(window, 'matchMedia', { writable: true, value: undefined });

    expect(mountViewport().viewport.isMobile.value).toBe(false);
  });
});
