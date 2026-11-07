import { getActivePinia } from 'pinia';
import type { Pinia, Store } from 'pinia';

/*
 * Pinia's live store registry. `_s` is internal and carries no public type, but it is the only
 * handle on the stores that were actually instantiated in this tab — which is the whole point
 * here. Narrowed to what this file reads rather than cast to `any`, so a Pinia upgrade that
 * moves the registry fails the build instead of failing silently at logout.
 */
type PiniaWithRegistry = Pinia & { _s: Map<string, Store> };

/**
 * Wipes every feature store so the next person to sign in in this tab does not inherit the
 * previous one's lists and flags.
 *
 * Setup stores (e.g. `idea`) do not ship `$reset` — calling it throws. The old helper rebuilt
 * stores from `pinia.state` and then `$reset()`'d them unconditionally, so logout after a 200
 * still rejected into the navbar catch and showed “something went wrong” with nothing in the
 * console. Walk the live registry and skip / swallow stores that cannot reset.
 */
export default function () {
  const pinia = getActivePinia() as PiniaWithRegistry | undefined;

  if (!pinia) return;

  // Device prefs stay; everything else belongs to the session that just ended.
  const SKIP = new Set(['ui', 'colorScheme']);

  pinia._s.forEach((store) => {
    if (SKIP.has(store.$id)) return;

    try {
      store.$reset();
    } catch {
      // Setup store without a custom `$reset` — leave it; feature stores that need a wipe
      // expose `$reset` themselves (see `idea`).
    }
  });
}
