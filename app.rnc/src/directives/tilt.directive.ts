import type { Directive } from 'vue';
import { prefersReducedMotionHelper } from '@Helpers';

/*
 * `v-tilt` — the element leans a few degrees toward the pointer, and settles back when it leaves.
 *
 * The directive writes the two angles (`--tilt-x`, `--tilt-y`) and marks the element
 * (`data-tilt`); the perspective transform and its `settle` transition live in
 * `styles/components/motion.css`. `v-tilt="4"` caps the lean at 4°; the default is 5°.
 *
 * Off under reduced motion — a surface that moves under the pointer is exactly the kind of
 * motion that preference asks us not to make — and off for a finger, where "toward the pointer"
 * has no meaning.
 */
const DEFAULT_MAX_DEGREES = 5;
const TILT_X_PROPERTY = '--tilt-x';
const TILT_Y_PROPERTY = '--tilt-y';

type TiltedElement = HTMLElement & {
  __tiltLeave?: () => void;
  __tiltMove?: (event: PointerEvent) => void;
};

// EVENTS
function onPointerLeave(element: HTMLElement) {
  element.style.setProperty(TILT_X_PROPERTY, '0deg');
  element.style.setProperty(TILT_Y_PROPERTY, '0deg');
}

function onPointerMove(element: HTMLElement, maxDegrees: number, event: PointerEvent) {
  if (event.pointerType !== 'mouse') return;

  const box = element.getBoundingClientRect();

  if (!box.width || !box.height) return;

  const x = (event.clientX - box.left) / box.width - 0.5;
  const y = (event.clientY - box.top) / box.height - 0.5;

  element.style.setProperty(TILT_X_PROPERTY, `${(-y * maxDegrees).toFixed(2)}deg`);
  element.style.setProperty(TILT_Y_PROPERTY, `${(x * maxDegrees).toFixed(2)}deg`);
}

const vTilt: Directive<TiltedElement, number | undefined> = {
  mounted(element, binding) {
    if (prefersReducedMotionHelper()) return;

    const maxDegrees = binding.value ?? DEFAULT_MAX_DEGREES;

    element.dataset.tilt = '';
    element.__tiltMove = (event) => onPointerMove(element, maxDegrees, event);
    element.__tiltLeave = () => onPointerLeave(element);
    element.addEventListener('pointermove', element.__tiltMove, { passive: true });
    element.addEventListener('pointerleave', element.__tiltLeave);
  },

  unmounted(element) {
    if (element.__tiltMove) element.removeEventListener('pointermove', element.__tiltMove);
    if (element.__tiltLeave) element.removeEventListener('pointerleave', element.__tiltLeave);

    delete element.__tiltMove;
    delete element.__tiltLeave;
  },
};

export { vTilt };
