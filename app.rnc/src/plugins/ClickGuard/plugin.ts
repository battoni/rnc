/**
 * Installs the guard as a single capture-phase `click` listener on `document`.
 *
 * Capture, and on `document`, because that is the only seat reached *before* the button's own
 * handler no matter who rendered the button — a PrimeVue `<Button>`, a hand-written `<button>`,
 * or a control inside a PrimeVue component we do not own. A swallowed click is stopped with
 * `stopImmediatePropagation`, so nothing downstream — the button, an ancestor, a form's implicit
 * submit — ever hears about it.
 *
 * The listener knows nothing about promises; it cannot, from where it sits. What it can see is
 * the app's own busy signal, so a click that *starts* API work keeps its button locked until that
 * work drains. The other half — a button that stays disabled while the promise its handler
 * returned is pending (`AGuardedButton` in lifeshifter) — was NOT ported; see RULES.md, Known Gaps.
 */

import { watch } from 'vue';
import { useAppBusy } from '@Composables';
import { guardClickEvent, holdClickGuard, releaseClickGuard } from './clickGuard';

let isListening = false;

// HELPERS

function holdWhileClickWorkIsInFlight(element: HTMLElement) {
  const { isBusy } = useAppBusy();

  // Already busy before the click: there is no rise to attribute to this click, so the plain time
  // window is all we can honestly claim. That is also the safety valve — a counter that leaked
  // somewhere cannot freeze every button in the app, it just stops buying them extra time.
  if (isBusy.value) return;

  // The handler runs synchronously inside the dispatch this listener opened, and the ApiProvider
  // raises the counter in the same turn. A microtask is the first moment the answer exists.
  queueMicrotask(() => extendHoldWhileBusy(element));
}

function extendHoldWhileBusy(element: HTMLElement) {
  const { isBusy } = useAppBusy();

  if (!isBusy.value) return;

  const stopWatching = watch(isBusy, (isStillBusy) => onBusyChange(isStillBusy, element));

  holdClickGuard(element, stopWatching);
}

// EVENTS

function onBusyChange(isStillBusy: boolean, element: HTMLElement) {
  if (isStillBusy) return;

  releaseClickGuard(element);
}

function onDocumentClickCapture(event: Event) {
  const element = guardClickEvent(event);

  if (!element) return;

  holdWhileClickWorkIsInFlight(element);
}

function startClickGuard() {
  if (isListening) return;

  isListening = true;

  document.addEventListener('click', onDocumentClickCapture, true);
}

function stopClickGuard() {
  isListening = false;

  document.removeEventListener('click', onDocumentClickCapture, true);
}

const ClickGuardPlugin = {
  install: startClickGuard,
};

export default ClickGuardPlugin;
export { startClickGuard, stopClickGuard };
