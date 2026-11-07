/**
 * The lock behind the app-wide double-click guard.
 *
 * One lock per button *element*, held in a `WeakMap` so an unmounted button takes its lock with
 * it. Two clicks on two different buttons never see each other; only a second click on the same
 * button inside the window is thrown away. That is the whole point — the bug is "one intent, two
 * records", not "the person is clicking too much".
 *
 * A lock is never permanent. Every path that holds one also arms a timer that releases it, so the
 * worst case of a handler that throws, hangs, or never settles is a button that waits, not a
 * button that dies.
 *
 * The opt-out is `data-allow-rapid-click`, honoured on the button or on any ancestor — the escape
 * hatch for controls whose whole job is to be pressed again immediately (a stepper, a month
 * arrow). It is read with `closest`, so putting it on a wrapper exempts everything inside.
 */

/** How long a button ignores a repeat of itself. Above pointer jitter, below a deliberate re-click. */
const GUARD_WINDOW_MS = 350;

/** The ceiling on an extended hold. Work slower than this releases the button anyway. */
const MAX_HOLD_MS = 8_000;

/** Only real buttons are guarded — see RULES.md for what that deliberately leaves out. */
const GUARDED_ELEMENT_SELECTOR = 'button';

/** Stamped on a locked button so a spec (or a person in devtools) can see the guard working. */
const LOCK_ATTRIBUTE = 'data-click-guard';

const OPT_OUT_ATTRIBUTE = 'data-allow-rapid-click';

/**
 * Controls that are *meant* to be hammered. The PrimeVue entries are sections we do not own and
 * cannot annotate at the call site: the InputNumber spinner (which is `mousedown`-driven anyway,
 * so this is belt and braces), the DatePicker month and year arrows, and a whole Paginator.
 */
const OPT_OUT_SELECTOR = [
  `[${OPT_OUT_ATTRIBUTE}]`,
  '[data-pc-section="incrementbutton"]',
  '[data-pc-section="decrementbutton"]',
  '[data-pc-section="prevbutton"]',
  '[data-pc-section="nextbutton"]',
  '[data-pc-name="paginator"]',
].join(',');

interface ClickGuardLock {
  onRelease: (() => void) | null;
  releaseTimeoutId: ReturnType<typeof setTimeout>;
}

const ACTIVE_LOCKS = new WeakMap<Element, ClickGuardLock>();

// HELPERS

function resolveGuardedElement(target: EventTarget | null): HTMLElement | null {
  if (!(target instanceof Element)) return null;

  const element = target.closest(GUARDED_ELEMENT_SELECTOR);

  return element instanceof HTMLElement ? element : null;
}

function allowsRapidClick(element: Element): boolean {
  return !!element.closest(OPT_OUT_SELECTOR);
}

function isClickGuardLocked(element: Element): boolean {
  return ACTIVE_LOCKS.has(element);
}

/** Drops the lock and runs whatever the holder left behind — a watcher to stop, usually. */
function releaseClickGuard(element: Element): void {
  const lock = ACTIVE_LOCKS.get(element);

  if (!lock) return;

  clearTimeout(lock.releaseTimeoutId);
  ACTIVE_LOCKS.delete(element);
  element.removeAttribute(LOCK_ATTRIBUTE);

  lock.onRelease?.();
}

/** Takes the short lock. Re-locking a locked element restarts the window rather than stacking. */
function lockClickGuard(element: Element, windowMs: number = GUARD_WINDOW_MS): void {
  releaseClickGuard(element);

  ACTIVE_LOCKS.set(element, {
    onRelease: null,
    releaseTimeoutId: setTimeout(() => releaseClickGuard(element), windowMs),
  });

  element.setAttribute(LOCK_ATTRIBUTE, 'locked');
}

/**
 * Stretches an existing lock past its window, for a click whose work is still in flight. The
 * caller releases it when the work settles; `MAX_HOLD_MS` releases it when the caller does not.
 * Holding an element that is no longer locked is a no-op that still runs `onRelease`, so the
 * caller's cleanup happens exactly once either way.
 */
function holdClickGuard(element: Element, onRelease?: () => void): void {
  const lock = ACTIVE_LOCKS.get(element);

  if (!lock) {
    onRelease?.();

    return;
  }

  clearTimeout(lock.releaseTimeoutId);

  /*
   * Run the previous holder's cleanup before taking its place. Overwriting it dropped a
   * `stopWatching` on the floor, and that watcher is created outside any effect scope — nobody
   * else would ever stop it, so it leaked for the life of the tab. Unreachable today; one
   * refactor away from not being.
   */
  lock.onRelease?.();

  lock.onRelease = onRelease ?? null;
  lock.releaseTimeoutId = setTimeout(() => releaseClickGuard(element), MAX_HOLD_MS);
}

/**
 * The decision, taken in the capture phase before the button's own handler runs.
 *
 * Returns the element that just took a fresh lock, so the caller can extend it. Returns `null`
 * when there is nothing to do: the click was not on a guarded button, the button opted out, or
 * the click was a repeat and has just been swallowed.
 */
function guardClickEvent(event: Event): HTMLElement | null {
  const element = resolveGuardedElement(event.target);

  if (!element || allowsRapidClick(element)) return null;

  if (isClickGuardLocked(element)) {
    event.preventDefault();
    event.stopImmediatePropagation();

    return null;
  }

  lockClickGuard(element);

  return element;
}

export {
  allowsRapidClick,
  GUARD_WINDOW_MS,
  guardClickEvent,
  holdClickGuard,
  isClickGuardLocked,
  LOCK_ATTRIBUTE,
  lockClickGuard,
  MAX_HOLD_MS,
  OPT_OUT_ATTRIBUTE,
  releaseClickGuard,
};
