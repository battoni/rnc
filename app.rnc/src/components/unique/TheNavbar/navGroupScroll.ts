/**
 * Bringing a group that was just opened into view, inside the navigation's own scroll area.
 *
 * A group near the bottom of the list opens below the fold: its children exist, they are in the
 * DOM, they are reachable by keyboard — and nobody sees them. The rail is the only thing that
 * moves; the page never scrolls, which is why this measures against the scroll container rather
 * than calling `scrollIntoView`, whose `block: 'nearest'` also walks every scrollable ancestor.
 *
 * Both `ONavbarDesktop` and `ONavbarDrawer` call this — the rail and the drawer are the same
 * navigation at two widths.
 */

/**
 * How far the scroll area must travel for an open group to fit, in pixels — negative to scroll
 * up. Two intents in one expression: never leave the header above the visible top (`groupTop`
 * caps it), and never scroll further than it takes to reveal the last child (the overflow
 * below). A group taller than the scroll area pins its header to the top and overflows below,
 * which is the right trade: the header is what names the children.
 */
function getNavGroupScrollDelta(groupTop: number, groupHeight: number, viewportHeight: number): number {
  return Math.min(groupTop, Math.max(0, groupTop + groupHeight - viewportHeight));
}

/**
 * The group's open height is measured as its header row plus its child list, never as the
 * group's own box: the children collapse as a `0fr → 1fr` grid track, so mid-animation — and at
 * the instant the class changes — that box still reports the collapsed height. The `<ul>` is
 * clipped by its parent, not shrunk, so it carries the real number the whole time.
 */
function revealNavGroup(scrollArea: HTMLElement | null, groupElement: HTMLElement | null) {
  if (!scrollArea || !groupElement) return;

  const headerRow = groupElement.firstElementChild as HTMLElement | null;
  const childList = groupElement.querySelector('ul');
  const openHeight = (headerRow?.offsetHeight ?? 0) + (childList?.offsetHeight ?? 0);
  const groupTop = groupElement.getBoundingClientRect().top - scrollArea.getBoundingClientRect().top;
  const delta = getNavGroupScrollDelta(groupTop, openHeight, scrollArea.clientHeight);

  if (Math.abs(delta) < 1) return;

  scrollArea.scrollTo({ top: scrollArea.scrollTop + delta, behavior: 'smooth' });
}

export { getNavGroupScrollDelta, revealNavGroup };
