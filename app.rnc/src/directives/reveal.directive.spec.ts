import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { vReveal } from './reveal.directive';

/*
 * `v-reveal` hides an element only once it has an observer to show it again. jsdom has no
 * IntersectionObserver, so the bare environment IS the "no observer" case; the stub below is
 * the browser that has one, and it is driven by hand.
 */
type IntersectCallback = (entries: IntersectionObserverEntry[]) => void;

const observed: Element[] = [];
const unobserved: Element[] = [];

let intersect: IntersectCallback = () => {};

class IntersectionObserverStub {
  constructor(callback: IntersectCallback) {
    intersect = callback;
  }

  observe(element: Element) {
    observed.push(element);
  }

  unobserve(element: Element) {
    unobserved.push(element);
  }

  disconnect() {}
}

function enters(element: Element) {
  intersect([{ isIntersecting: true, target: element } as IntersectionObserverEntry]);
}

function withObserver() {
  vi.stubGlobal('IntersectionObserver', IntersectionObserverStub);
}

function withReducedMotion() {
  vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true } as MediaQueryList);
}

function mountRevealed(stagger = false) {
  const items = ref(['a', 'b']);
  const Host = defineComponent({
    directives: { reveal: vReveal },
    setup: () => ({ items }),
    template: stagger
      ? '<ul v-reveal.stagger><li v-for="item in items" :key="item">{{ item }}</li></ul>'
      : '<section v-reveal><p>copy</p></section>',
  });

  return { items, wrapper: mount(Host) };
}

afterEach(() => {
  observed.length = 0;
  unobserved.length = 0;
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  vi.resetModules();
});

describe('vReveal', () => {
  it('leaves the element visible where there is no IntersectionObserver to ever show it', () => {
    const { wrapper } = mountRevealed();

    expect(wrapper.element.hasAttribute('data-reveal')).toBe(false);
  });

  it('leaves the element visible when the person asked for less motion', () => {
    withObserver();
    withReducedMotion();

    const { wrapper } = mountRevealed();

    expect(wrapper.element.hasAttribute('data-reveal')).toBe(false);
    expect(observed).toHaveLength(0);
  });

  it('hides the element until it scrolls into view, then shows it once and stops watching', () => {
    withObserver();

    const { wrapper } = mountRevealed();

    expect(wrapper.attributes('data-reveal')).toBe('pending');
    expect(observed).toEqual([wrapper.element]);

    enters(wrapper.element);

    expect(wrapper.attributes('data-reveal')).toBe('shown');
    expect(unobserved).toEqual([wrapper.element]);
  });

  it('numbers the children of a staggered list, and a child that arrives later too', async () => {
    const { items, wrapper } = mountRevealed(true);

    expect(wrapper.element.hasAttribute('data-stagger')).toBe(true);
    expect(wrapper.findAll('li').map((item) => item.element.style.getPropertyValue('--reveal-index'))).toEqual([
      '0',
      '1',
    ]);

    items.value.push('c');
    await nextTick();

    expect(wrapper.findAll('li')[2]!.element.style.getPropertyValue('--reveal-index')).toBe('2');
  });

  it('stops watching an element that leaves the page before it was seen', () => {
    withObserver();

    const { wrapper } = mountRevealed();

    wrapper.unmount();

    expect(unobserved).toEqual([wrapper.element]);
  });
});
