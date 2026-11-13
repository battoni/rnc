import { ref } from 'vue';
import type { ClientEvent } from '../interfaces';
import { GetClientEventsService } from '../services';

/**
 * One client's timeline as a screen holds it (rnc-2 R20, R22): `load` reads it, `prepend` puts the
 * line a notice or a review just wrote on top — the API's own answer, never a refetch (celer-07).
 * An answer that lands after another client was opened is dropped, so a slow one never paints one
 * client's lines under another's name.
 */
export default function useClientEvents() {
  const clientId = ref<number | null>(null);
  const events = ref<ClientEvent[]>([]);
  const failed = ref(false);
  const loading = ref(false);

  // HELPERS
  function isCurrent(id: number): boolean {
    return clientId.value === id;
  }

  // EVENTS
  function load(id: number): Promise<void> {
    clientId.value = id;
    events.value = [];
    failed.value = false;
    loading.value = true;

    return GetClientEventsService(id)
      .then((response) => onLoaded(id, response.data))
      .catch(() => onFailed(id));
  }

  function onLoaded(id: number, lines: ClientEvent[]) {
    if (!isCurrent(id)) return;

    events.value = lines;
    loading.value = false;
  }

  function onFailed(id: number) {
    if (!isCurrent(id)) return;

    failed.value = true;
    loading.value = false;
  }

  function prepend(event: ClientEvent) {
    if (!isCurrent(event.client_id)) return;

    events.value = [event, ...events.value];
  }

  return { clientId, events, failed, load, loading, prepend };
}
