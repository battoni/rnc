/*
 * One IntersectionObserver for every directive that waits for an element to scroll into view
 * (`vReveal`, `vCountUp`). One instead of one per element: a landing has thirty revealed
 * blocks, and thirty observers each firing their own callback cost more than the motion.
 *
 * The callback runs ONCE, the first time the element is in view, and the element is then
 * forgotten. An element that never enters the viewport is never called — which is why a
 * directive must leave the element visible until it has confirmed an observer exists.
 *
 * `rootMargin` pulls the bottom edge up by 8% so the entrance starts just before the element
 * would be seen, and `threshold` asks for 8% of it: enough to call it "in view", small enough
 * that a tall section still fires near its top.
 */
const OBSERVER_OPTIONS: IntersectionObserverInit = { rootMargin: '0px 0px -8% 0px', threshold: 0.08 };

const callbacks = new WeakMap<Element, () => void>();

let observer: IntersectionObserver | null = null;

function observerInstance(): IntersectionObserver | null {
  if (observer) return observer;
  if (!('IntersectionObserver' in window)) return null;

  observer = new IntersectionObserver(onIntersect, OBSERVER_OPTIONS);

  return observer;
}

/** `true` when the browser can tell us; `false` means "do not hide anything, it will never show". */
function canObserveInView(): boolean {
  return observerInstance() !== null;
}

function observeInView(element: Element, callback: () => void): void {
  const instance = observerInstance();

  if (!instance) return;

  callbacks.set(element, callback);
  instance.observe(element);
}

function unobserveInView(element: Element): void {
  callbacks.delete(element);
  observer?.unobserve(element);
}

// EVENTS
function onIntersect(entries: IntersectionObserverEntry[]) {
  for (const entry of entries) {
    if (!entry.isIntersecting) continue;

    const callback = callbacks.get(entry.target);

    unobserveInView(entry.target);
    callback?.();
  }
}

export { canObserveInView, observeInView, unobserveInView };
