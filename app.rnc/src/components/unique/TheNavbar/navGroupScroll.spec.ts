import { describe, expect, it, vi } from 'vitest';
import { getNavGroupScrollDelta, revealNavGroup } from './navGroupScroll';

interface GroupGeometry {
  childListHeight: number;
  headerHeight: number;
  scrollAreaHeight: number;
  scrollAreaTop: number;
  scrollTop: number;
  groupTop: number;
}

const DEFAULT_GEOMETRY: GroupGeometry = {
  childListHeight: 120,
  headerHeight: 40,
  scrollAreaHeight: 300,
  scrollAreaTop: 0,
  scrollTop: 0,
  groupTop: 0,
};

/** jsdom has no layout, so every number the helper reads is stated here instead of measured. */
function stubHeight(element: HTMLElement, offsetHeight: number) {
  Object.defineProperty(element, 'offsetHeight', { configurable: true, value: offsetHeight });
}

function stubTop(element: HTMLElement, top: number) {
  element.getBoundingClientRect = () => ({ top }) as DOMRect;
}

function buildNavGroup(geometry: Partial<GroupGeometry> = {}) {
  const { childListHeight, groupTop, headerHeight, scrollAreaHeight, scrollAreaTop, scrollTop } = {
    ...DEFAULT_GEOMETRY,
    ...geometry,
  };

  const scrollArea = document.createElement('div');
  const groupElement = document.createElement('div');
  const headerRow = document.createElement('div');
  const childList = document.createElement('ul');

  groupElement.append(headerRow, childList);
  scrollArea.append(groupElement);

  stubTop(scrollArea, scrollAreaTop);
  stubTop(groupElement, groupTop);
  stubHeight(headerRow, headerHeight);
  stubHeight(childList, childListHeight);

  Object.defineProperty(scrollArea, 'clientHeight', { configurable: true, value: scrollAreaHeight });
  Object.defineProperty(scrollArea, 'scrollTop', { configurable: true, value: scrollTop, writable: true });

  scrollArea.scrollTo = vi.fn();

  return { groupElement, scrollArea };
}

describe('getNavGroupScrollDelta', () => {
  it('asks for nothing when the open group already fits', () => {
    expect(getNavGroupScrollDelta(20, 160, 300)).toBe(0);
  });

  it('moves just far enough to show the last child', () => {
    // Bottom of the group sits 60px past the fold, and the header has 240px of room above it.
    expect(getNavGroupScrollDelta(240, 120, 300)).toBe(60);
  });

  it('pins the header to the top when the group is taller than the scroll area', () => {
    // 500px of group into a 300px area: the header is what names the children, so it wins.
    expect(getNavGroupScrollDelta(240, 500, 300)).toBe(240);
  });

  it('brings a header that drifted above the top edge back into view', () => {
    expect(getNavGroupScrollDelta(-30, 120, 300)).toBe(-30);
  });
});

describe('revealNavGroup', () => {
  it('scrolls the group into view when its children open below the fold', () => {
    const { groupElement, scrollArea } = buildNavGroup({ groupTop: 250, scrollTop: 100 });

    revealNavGroup(scrollArea, groupElement);

    // 250 + (40 + 120) - 300 = 110 past the fold, and the header has 250px above it.
    expect(scrollArea.scrollTo).toHaveBeenCalledWith({ top: 210, behavior: 'smooth' });
  });

  it('leaves the scroll position alone when the whole group is already visible', () => {
    const { groupElement, scrollArea } = buildNavGroup({ groupTop: 20 });

    revealNavGroup(scrollArea, groupElement);

    expect(scrollArea.scrollTo).not.toHaveBeenCalled();
  });

  it('measures the child list even while the track still reports the collapsed height', () => {
    // The group's own box is 0-height here — the `<ul>` is clipped by its parent, not shrunk,
    // which is why it is the thing that carries the real number during the animation.
    const { groupElement, scrollArea } = buildNavGroup({ groupTop: 290, headerHeight: 40 });

    stubHeight(groupElement, 0);
    revealNavGroup(scrollArea, groupElement);

    expect(scrollArea.scrollTo).toHaveBeenCalledWith({ top: 150, behavior: 'smooth' });
  });

  it('does nothing without a scroll area or a group', () => {
    const { groupElement, scrollArea } = buildNavGroup();

    revealNavGroup(null, groupElement);
    revealNavGroup(scrollArea, null);

    expect(scrollArea.scrollTo).not.toHaveBeenCalled();
  });
});
