import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AWordRise from './AWordRise.vue';

describe('AWordRise', () => {
  it('reads as the sentence it was given, one space between words', () => {
    const wrapper = mount(AWordRise, { props: { startIndex: 0, text: 'A CBS começa em 2027.' } });

    expect(wrapper.text()).toBe('A CBS começa em 2027.');
    expect(wrapper.element.textContent).toBe('A CBS começa em 2027.');
  });

  it('numbers each word from the start index it was given', () => {
    const wrapper = mount(AWordRise, { props: { startIndex: 3, text: 'Sua empresa sabe?' } });

    expect(
      wrapper.findAll<HTMLElement>('.motion-word').map((word) => word.element.style.getPropertyValue('--reveal-index'))
    ).toEqual(['3', '4', '5']);
  });
});
