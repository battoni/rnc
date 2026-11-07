import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { vTilt } from './tilt.directive';

function mountTilted(max?: number) {
  const Host = defineComponent({
    directives: { tilt: vTilt },
    template: max === undefined ? '<div v-tilt>card</div>' : `<div v-tilt="${max}">card</div>`,
  });
  const wrapper = mount(Host);

  vi.spyOn(wrapper.element, 'getBoundingClientRect').mockReturnValue({
    height: 100,
    left: 0,
    top: 0,
    width: 200,
  } as DOMRect);

  return wrapper;
}

function pointer(element: Element, type: string, clientX = 0, clientY = 0, pointerType = 'mouse') {
  const event = new MouseEvent(type, { clientX, clientY }) as PointerEvent;

  Object.defineProperty(event, 'pointerType', { value: pointerType });
  element.dispatchEvent(event);
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('vTilt', () => {
  it('leans toward the mouse, up to 5° by default, and settles back when it leaves', () => {
    const wrapper = mountTilted();

    expect(wrapper.attributes('data-tilt')).toBe('');

    // Bottom-right corner: the card tips down (negative X) and to the right (positive Y).
    pointer(wrapper.element, 'pointermove', 200, 100);

    expect(wrapper.element.style.getPropertyValue('--tilt-x')).toBe('-2.50deg');
    expect(wrapper.element.style.getPropertyValue('--tilt-y')).toBe('2.50deg');

    pointer(wrapper.element, 'pointerleave');

    expect(wrapper.element.style.getPropertyValue('--tilt-x')).toBe('0deg');
    expect(wrapper.element.style.getPropertyValue('--tilt-y')).toBe('0deg');
  });

  it('takes the cap from its value', () => {
    const wrapper = mountTilted(10);

    pointer(wrapper.element, 'pointermove', 200, 50);

    expect(wrapper.element.style.getPropertyValue('--tilt-y')).toBe('5.00deg');
  });

  it('does not lean for a finger', () => {
    const wrapper = mountTilted();

    pointer(wrapper.element, 'pointermove', 200, 100, 'touch');

    expect(wrapper.element.style.getPropertyValue('--tilt-y')).toBe('');
  });

  it('does not lean at all when the person asked for less motion', () => {
    vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true } as MediaQueryList);

    const wrapper = mountTilted();

    pointer(wrapper.element, 'pointermove', 200, 100);

    expect(wrapper.element.hasAttribute('data-tilt')).toBe(false);
    expect(wrapper.element.style.getPropertyValue('--tilt-y')).toBe('');
  });
});
