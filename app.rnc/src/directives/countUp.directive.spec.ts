import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { vCountUp } from './countUp.directive';

/*
 * `v-count-up` never touches the text: the DOM reads "500+" from the first frame and only the
 * painted-over `data-show` rolls. Driven with a hand-held IntersectionObserver and fake frames.
 */
type IntersectCallback = (entries: IntersectionObserverEntry[]) => void;

let intersect: IntersectCallback = () => {};

class IntersectionObserverStub {
  constructor(callback: IntersectCallback) {
    intersect = callback;
  }

  observe() {}

  unobserve() {}

  disconnect() {}
}

function enters(element: Element) {
  intersect([{ isIntersecting: true, target: element } as IntersectionObserverEntry]);
}

function mountCounted(text: string) {
  const Host = defineComponent({
    directives: { countUp: vCountUp },
    template: `<strong v-count-up>${text}</strong>`,
  });

  return mount(Host);
}

beforeEach(() => {
  vi.stubGlobal('IntersectionObserver', IntersectionObserverStub);
  vi.useFakeTimers({ toFake: ['requestAnimationFrame', 'cancelAnimationFrame', 'performance'] });
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  vi.resetModules();
});

describe('vCountUp', () => {
  it('keeps the real text, rolls the painted value from zero and clears up when it lands', () => {
    const wrapper = mountCounted('500+');

    expect(wrapper.element.hasAttribute('data-counting')).toBe(false);

    enters(wrapper.element);

    expect(wrapper.text()).toBe('500+');
    expect(wrapper.attributes('data-counting')).toBe('');
    expect(wrapper.attributes('data-show')).toBe('0+');

    vi.advanceTimersByTime(300);

    const midway = Number(wrapper.attributes('data-show')!.replace('+', ''));

    expect(midway).toBeGreaterThan(0);
    expect(midway).toBeLessThan(500);

    vi.advanceTimersByTime(1200);

    expect(wrapper.text()).toBe('500+');
    expect(wrapper.element.hasAttribute('data-counting')).toBe(false);
    expect(wrapper.element.hasAttribute('data-show')).toBe(false);
  });

  it('rolls only the number and keeps the words around it', () => {
    const wrapper = mountCounted('35 anos');

    enters(wrapper.element);

    expect(wrapper.attributes('data-show')).toBe('0 anos');

    vi.advanceTimersByTime(1200);

    expect(wrapper.text()).toBe('35 anos');
  });

  it('does nothing for a figure with no number in it, or whose number is a name rather than a count', () => {
    for (const text of ['RNC', 'ISO 9001']) {
      const wrapper = mountCounted(text);

      enters(wrapper.element);
      vi.advanceTimersByTime(100);

      expect(wrapper.element.hasAttribute('data-counting')).toBe(false);
      expect(wrapper.element.hasAttribute('data-show')).toBe(false);
    }
  });

  it('rolls in plain digits, never a grouped 1.000 on the way to a plain 1200', () => {
    const wrapper = mountCounted('1200+');

    enters(wrapper.element);
    vi.advanceTimersByTime(1000);

    expect(wrapper.attributes('data-show')).toMatch(/^\d+\+$/);
  });

  it('does nothing when the person asked for less motion', () => {
    vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true } as MediaQueryList);

    const wrapper = mountCounted('500+');

    enters(wrapper.element);
    vi.advanceTimersByTime(100);

    expect(wrapper.element.hasAttribute('data-counting')).toBe(false);
    expect(wrapper.text()).toBe('500+');
  });
});
