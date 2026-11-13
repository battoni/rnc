import type { Directive } from 'vue';
import { prefersReducedMotionHelper } from '@Helpers';
import { canObserveInView, observeInView, unobserveInView } from './inView';

/*
 * `v-count-up` — a figure that opens with a number ("35 anos", "500+") rolls from zero to its
 * value the first time the element scrolls into view. A figure whose number comes later ("ISO
 * 9001") is a name, not a count, and is left alone.
 *
 * The element KEEPS its real text the whole time. While it rolls, the stylesheet paints that
 * text transparent and shows `data-show` — the in-between value — in a pseudo-element on top
 * (`styles/components/motion.css`). So the DOM, a screen reader and a test all read the final
 * number from the first frame; only the eye sees it arrive. Nothing here ever writes
 * `textContent`.
 *
 * The roll lasts two `--motion-settle`: one settle reads as a flicker on a three-digit number,
 * and the token is read from the stylesheet so a reduced-motion collapse reaches it too — on
 * top of the directive not rolling at all under that preference.
 */
const DEFAULT_SETTLE_MS = 520;
const SETTLES_PER_ROLL = 2;
const FIRST_NUMBER = /\d+/;

type RollingElement = HTMLElement & { __countUpFrame?: number };

// HELPERS
function easeOut(progress: number): number {
  return 1 - (1 - progress) ** 3;
}

/** Plain digits, like the text it stands in for: "500+" never rolls through "1.000" on the way. */
function formatLike(value: number): string {
  return String(Math.round(value));
}

function settleMs(): number {
  const declared = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--motion-settle'));

  return Number.isFinite(declared) && declared > 0 ? declared : DEFAULT_SETTLE_MS;
}

function roll(element: RollingElement, before: string, target: number, after: string) {
  const duration = settleMs() * SETTLES_PER_ROLL;
  const startedAt = performance.now();

  element.dataset.counting = '';

  function onFrame(now: number) {
    const progress = Math.min(1, (now - startedAt) / duration);

    element.dataset.show = before + formatLike(target * easeOut(progress)) + after;

    if (progress < 1) {
      element.__countUpFrame = requestAnimationFrame(onFrame);
      return;
    }

    stop(element);
  }

  element.dataset.show = before + formatLike(0) + after;
  element.__countUpFrame = requestAnimationFrame(onFrame);
}

function stop(element: RollingElement) {
  if (element.__countUpFrame !== undefined) cancelAnimationFrame(element.__countUpFrame);

  delete element.__countUpFrame;
  delete element.dataset.counting;
  delete element.dataset.show;
}

const vCountUp: Directive<HTMLElement> = {
  mounted(element) {
    const text = element.textContent ?? '';
    const match = FIRST_NUMBER.exec(text);

    // Only a figure that OPENS with its number is a count ("35 anos", "500+"). "ISO 9001" is a
    // name, and a name that rolls through 5.640 on its way to 9001 reads as a different standard.
    if (!match || match.index !== 0 || !canObserveInView() || prefersReducedMotionHelper()) return;

    const target = Number(match[0]);

    if (target === 0) return;

    const before = text.slice(0, match.index);
    const after = text.slice(match.index + match[0].length);

    observeInView(element, () => roll(element, before, target, after));
  },

  unmounted(element) {
    unobserveInView(element);
    stop(element);
  },
};

export { vCountUp };
