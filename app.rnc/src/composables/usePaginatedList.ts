import { ref } from 'vue';
import type { Ref } from 'vue';
import type { ApiResponse, PaginateResult } from '@Providers';

interface PaginatedFetcher<T> {
  (params: { page: number; per_page: number }): Promise<ApiResponse<PaginateResult<T>>>;
}

interface PaginatedListOptions {
  onError?: () => void;
  perPage?: number;
}

/**
 * Standard state + fetch loop for a server-paginated list view: holds
 * items/page/total/loading, calls the fetcher with `{ page, per_page }`, and
 * reads the Laravel `PaginateResult` shape back. Pair with `<OPaginator>`.
 *
 * Granular mutations go through `removeItem`/`prependItem` rather than writing
 * `items` directly — on a paginated list the row count and the total have to
 * move together, or the paginator keeps offering a page that no longer exists.
 */
function usePaginatedList<T extends { id: number }>(fetcher: PaginatedFetcher<T>, options: PaginatedListOptions = {}) {
  const perPage = options.perPage ?? 15;

  const items = ref<T[]>([]) as Ref<T[]>;
  const loading = ref(false);
  const loadFailed = ref(false);
  const page = ref(1);
  const total = ref(0);

  // EVENTS
  /**
   * Returned, so a caller that refreshes after a save can wait for it. `loadFailed` tells a failed
   * load from an empty list: they ask opposite things of the reader (celer-07).
   */
  function load(): Promise<void> {
    loading.value = true;
    loadFailed.value = false;

    return fetcher({ page: page.value, per_page: perPage })
      .then(({ data }) => {
        items.value = data.data ?? [];
        total.value = data.total ?? 0;
      })
      .catch(() => {
        loadFailed.value = true;

        options.onError?.();
      })
      .finally(() => (loading.value = false));
  }

  function onPage(nextPage: number) {
    page.value = nextPage;
    load();
  }

  /**
   * Drop a row after a delete/archive. Emptying a page beyond the first steps
   * back one page instead of leaving the view blank.
   */
  function removeItem(id: number) {
    items.value = items.value.filter((currentItem) => currentItem.id !== id);
    total.value = Math.max(0, total.value - 1);

    const hasEmptiedPage = items.value.length === 0 && page.value > 1;

    if (!hasEmptiedPage) return;

    page.value -= 1;
    load();
  }

  /**
   * Show a newly created row. It belongs at the top of page 1, so anywhere else
   * we jump there and refetch rather than splicing it into the wrong page.
   */
  function prependItem(item: T) {
    total.value += 1;

    if (page.value > 1) {
      page.value = 1;
      load();
      return;
    }

    items.value = [item, ...items.value].slice(0, perPage);
  }

  return { items, load, loadFailed, loading, onPage, page, perPage, prependItem, removeItem, total };
}

export { usePaginatedList };
export type { PaginatedFetcher, PaginatedListOptions };
