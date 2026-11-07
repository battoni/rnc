import type { Directive } from 'vue';
import { prefersReducedMotionHelper } from '@Helpers';
import { canObserveInView, observeInView, unobserveInView } from './inView';

/*
 * `v-reveal` — the element rises into place the first time it scrolls into view.
 *
 * The directive only ever ADDS a state the stylesheet keys on (`data-reveal="pending"` hides,
 * `data-reveal="shown"` plays the rise; `styles/components/motion.css`), and it adds `pending`
 * only after confirming an IntersectionObserver exists and the person has not asked for less
 * motion. Markup it never touched is plain, visible markup: a section can never be left at
 * opacity 0 because a script failed or an observer never fired.
 *
 * `v-reveal.stagger` numbers the element's children (`--reveal-index`) so each arrives one
 * `--motion-stagger` after the last. The index is set on mount and again on update, so a list
 * that grows after its data arrives is numbered too.
 */
const REVEAL_PENDING = 'pending';
const REVEAL_SHOWN = 'shown';
const REVEAL_INDEX_PROPERTY = '--reveal-index';

type RevealModifiers = 'stagger';

// HELPERS
function numberChildren(element: HTMLElement) {
  Array.from(element.children).forEach((child, index) =>
    (child as HTMLElement).style.setProperty(REVEAL_INDEX_PROPERTY, String(index))
  );
}

// EVENTS
function onInView(element: HTMLElement) {
  element.dataset.reveal = REVEAL_SHOWN;
}

const vReveal: Directive<HTMLElement, undefined, RevealModifiers> = {
  mounted(element, binding) {
    if (binding.modifiers.stagger) {
      element.dataset.stagger = '';
      numberChildren(element);
    }

    if (!canObserveInView() || prefersReducedMotionHelper()) return;

    element.dataset.reveal = REVEAL_PENDING;
    observeInView(element, () => onInView(element));
  },

  updated(element, binding) {
    if (binding.modifiers.stagger) numberChildren(element);
  },

  unmounted(element) {
    unobserveInView(element);
  },
};

export { vReveal };
