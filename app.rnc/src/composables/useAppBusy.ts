import { computed, ref } from 'vue';

/**
 * Whether the app is doing something the person is waiting on — a navigation resolving, a request
 * in flight. One shared signal, module-scoped like `useSingleTip`: ephemeral session state, never
 * persisted, deliberately not a store so it stays free of everything else the stores carry.
 *
 * Two feeds, two shapes, because they fail differently:
 *
 * - **Requests are a counter.** `beginRequest`/`endRequest` are called by the ApiProvider around
 *   every call, and `.finally` guarantees the pair — overlapping requests just stack.
 * - **Navigation is a boolean.** Vue Router replaces a redirected navigation with a fresh cycle
 *   and only the final one reaches `afterEach`, so a counter would leak a `begin` for every
 *   redirect. A boolean absorbs that: `beforeEach` raises it as many times as it likes, the one
 *   `afterEach` (or `onError`) that ends the story lowers it.
 *
 * The consumer is the mark: `ALogo` loops while `isBusy` holds. Nothing else should key
 * behaviour off this — a screen that needs its own loading state keeps its own ref.
 */
const pendingRequests = ref(0);
const isNavigating = ref(false);

const isBusy = computed(() => isNavigating.value || pendingRequests.value > 0);

function beginRequest() {
  pendingRequests.value += 1;
}

function endRequest() {
  pendingRequests.value = Math.max(0, pendingRequests.value - 1);
}

function setNavigating(isPending: boolean) {
  isNavigating.value = isPending;
}

/** For specs only — module state survives between tests otherwise. */
function resetAppBusyState() {
  pendingRequests.value = 0;
  isNavigating.value = false;
}

function useAppBusy() {
  return { beginRequest, endRequest, isBusy, setNavigating };
}

export { beginRequest, endRequest, resetAppBusyState, setNavigating, useAppBusy };
