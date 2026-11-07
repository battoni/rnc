import type { Directive } from 'vue';

/*
 * `v-spotlight` — a soft brand-coloured light follows the pointer across the element.
 *
 * The directive only reports where the pointer is (`--spot-x`, `--spot-y`, in px from the
 * element's corner) and marks the element (`data-spotlight`); the light itself is a pseudo-element
 * in `styles/components/motion.css`, so the colour is the theme's and the fade is a token.
 *
 * Not switched off under reduced motion: nothing moves or enters, a hover tint only follows
 * the pointer the person is already moving. A finger gets no spotlight — `pointermove` from a
 * touch is a drag, and lighting the card under it says nothing.
 */
const SPOT_X_PROPERTY = '--spot-x';
const SPOT_Y_PROPERTY = '--spot-y';

type LitElement = HTMLElement & { __spotlight?: (event: PointerEvent) => void };

// EVENTS
function onPointerMove(element: HTMLElement, event: PointerEvent) {
  if (event.pointerType !== 'mouse') return;

  const box = element.getBoundingClientRect();

  element.style.setProperty(SPOT_X_PROPERTY, `${Math.round(event.clientX - box.left)}px`);
  element.style.setProperty(SPOT_Y_PROPERTY, `${Math.round(event.clientY - box.top)}px`);
}

const vSpotlight: Directive<LitElement> = {
  mounted(element) {
    element.dataset.spotlight = '';
    element.__spotlight = (event) => onPointerMove(element, event);
    element.addEventListener('pointermove', element.__spotlight, { passive: true });
  },

  unmounted(element) {
    if (element.__spotlight) element.removeEventListener('pointermove', element.__spotlight);

    delete element.__spotlight;
  },
};

export { vSpotlight };
