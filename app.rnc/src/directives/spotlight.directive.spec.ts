import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { vSpotlight } from './spotlight.directive';

function mountLit() {
  const Host = defineComponent({
    directives: { spotlight: vSpotlight },
    template: '<article v-spotlight>card</article>',
  });
  const wrapper = mount(Host);

  vi.spyOn(wrapper.element, 'getBoundingClientRect').mockReturnValue({
    height: 100,
    left: 20,
    top: 10,
    width: 200,
  } as DOMRect);

  return wrapper;
}

function move(element: Element, pointerType: string, clientX: number, clientY: number) {
  const event = new MouseEvent('pointermove', { clientX, clientY }) as PointerEvent;

  Object.defineProperty(event, 'pointerType', { value: pointerType });
  element.dispatchEvent(event);
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('vSpotlight', () => {
  it('marks the element and reports where the mouse is, from its own corner', () => {
    const wrapper = mountLit();

    expect(wrapper.attributes('data-spotlight')).toBe('');

    move(wrapper.element, 'mouse', 70, 60);

    expect(wrapper.element.style.getPropertyValue('--spot-x')).toBe('50px');
    expect(wrapper.element.style.getPropertyValue('--spot-y')).toBe('50px');
  });

  it('ignores a finger dragging over the card', () => {
    const wrapper = mountLit();

    move(wrapper.element, 'touch', 70, 60);

    expect(wrapper.element.style.getPropertyValue('--spot-x')).toBe('');
  });

  it('stops listening once the element leaves the page', () => {
    const wrapper = mountLit();
    const element = wrapper.element;

    wrapper.unmount();
    move(element, 'mouse', 70, 60);

    expect(element.style.getPropertyValue('--spot-x')).toBe('');
  });
});
