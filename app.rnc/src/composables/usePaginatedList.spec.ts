import { flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import type { PaginateResult } from '@Providers';
import { usePaginatedList } from './usePaginatedList';

interface Row {
  id: number;
}

/** The paginator fields the composable ignores — filled in so the stub matches the real shape. */
const PAGINATOR_ENVELOPE = {
  first_page_url: '',
  from: 1,
  last_page: 1,
  last_page_url: '',
  links: [],
  next_page_url: null,
  path: '',
  prev_page_url: null,
  to: 1,
};

function buildPage(page: number, data: Row[], total: number, perPage: number): PaginateResult<Row> {
  return { ...PAGINATOR_ENVELOPE, current_page: page, data, per_page: perPage, total };
}

function buildFetcher(pages: Record<number, { data: Row[]; total: number }>, perPage = 15) {
  return vi.fn(({ page }: { page: number; per_page: number }) => {
    const requested = pages[page] ?? { data: [], total: 0 };

    return Promise.resolve({ data: buildPage(page, requested.data, requested.total, perPage) });
  });
}

function rows(...ids: number[]): Row[] {
  return ids.map((id) => ({ id }));
}

describe('usePaginatedList', () => {
  it('loads the first page and reads items and total off the paginator', async () => {
    const fetcher = buildFetcher({ 1: { data: rows(1, 2), total: 7 } });
    const list = usePaginatedList<Row>(fetcher, { perPage: 2 });

    list.load();
    await flushPromises();

    expect(fetcher).toHaveBeenCalledWith({ page: 1, per_page: 2 });
    expect(list.items.value).toEqual(rows(1, 2));
    expect(list.total.value).toBe(7);
    expect(list.loading.value).toBe(false);
  });

  it('refetches at the requested page on onPage', async () => {
    const fetcher = buildFetcher({
      1: { data: rows(1, 2), total: 4 },
      2: { data: rows(3, 4), total: 4 },
    });
    const list = usePaginatedList<Row>(fetcher, { perPage: 2 });

    list.onPage(2);
    await flushPromises();

    expect(list.page.value).toBe(2);
    expect(list.items.value).toEqual(rows(3, 4));
  });

  it('calls onError and stops loading when the fetch rejects', async () => {
    const onError = vi.fn();
    const fetcher = vi.fn(() => Promise.reject(new Error('boom')));
    const list = usePaginatedList<Row>(fetcher, { onError });

    list.load();
    await flushPromises();

    expect(onError).toHaveBeenCalled();
    expect(list.loading.value).toBe(false);
  });

  // A failed load is not an empty list: the view needs to know which one it is (celer-07).
  it('says the last load failed, and forgets it when the next one starts', async () => {
    const fetcher = vi
      .fn()
      .mockRejectedValueOnce(new Error('boom'))
      .mockResolvedValueOnce({ data: buildPage(1, rows(1), 1, 15) });
    const list = usePaginatedList<Row>(fetcher);

    expect(list.loadFailed.value).toBe(false);

    list.load();
    await flushPromises();

    expect(list.loadFailed.value).toBe(true);
    expect(list.items.value).toEqual([]);

    list.load();

    expect(list.loadFailed.value).toBe(false);

    await flushPromises();

    expect(list.items.value).toEqual(rows(1));
  });

  it('returns the load, so a caller can wait for the refresh it asked for', async () => {
    const fetcher = buildFetcher({ 1: { data: rows(1), total: 1 } });
    const list = usePaginatedList<Row>(fetcher);

    const PENDING = list.load();

    expect(PENDING).toBeInstanceOf(Promise);

    await PENDING;

    expect(list.items.value).toEqual(rows(1));
  });

  it('removeItem drops the row and decrements the total', async () => {
    const fetcher = buildFetcher({ 1: { data: rows(1, 2, 3), total: 3 } });
    const list = usePaginatedList<Row>(fetcher, { perPage: 3 });

    list.load();
    await flushPromises();

    list.removeItem(2);

    expect(list.items.value).toEqual(rows(1, 3));
    expect(list.total.value).toBe(2);
  });

  it('removeItem steps back a page when it empties a page beyond the first', async () => {
    const fetcher = buildFetcher({
      1: { data: rows(1, 2), total: 3 },
      2: { data: rows(3), total: 3 },
    });
    const list = usePaginatedList<Row>(fetcher, { perPage: 2 });

    list.onPage(2);
    await flushPromises();

    list.removeItem(3);
    await flushPromises();

    expect(list.page.value).toBe(1);
    expect(list.items.value).toEqual(rows(1, 2));
  });

  it('removeItem stays put when emptying the first page', async () => {
    const fetcher = buildFetcher({ 1: { data: rows(1), total: 1 } });
    const list = usePaginatedList<Row>(fetcher, { perPage: 2 });

    list.load();
    await flushPromises();

    list.removeItem(1);

    expect(list.page.value).toBe(1);
    expect(list.items.value).toEqual([]);
    expect(list.total.value).toBe(0);
  });

  it('prependItem puts the new row on top of page one and counts it', async () => {
    const fetcher = buildFetcher({ 1: { data: rows(1, 2), total: 2 } });
    const list = usePaginatedList<Row>(fetcher, { perPage: 3 });

    list.load();
    await flushPromises();

    list.prependItem({ id: 9 });

    expect(list.items.value).toEqual(rows(9, 1, 2));
    expect(list.total.value).toBe(3);
  });

  it('prependItem keeps the page at its size, spilling the last row over', async () => {
    const fetcher = buildFetcher({ 1: { data: rows(1, 2), total: 5 } });
    const list = usePaginatedList<Row>(fetcher, { perPage: 2 });

    list.load();
    await flushPromises();

    list.prependItem({ id: 9 });

    expect(list.items.value).toEqual(rows(9, 1));
    expect(list.total.value).toBe(6);
  });

  it('prependItem returns to page one instead of splicing into a later page', async () => {
    const fetcher = buildFetcher({
      1: { data: rows(1, 2), total: 3 },
      2: { data: rows(3), total: 3 },
    });
    const list = usePaginatedList<Row>(fetcher, { perPage: 2 });

    list.onPage(2);
    await flushPromises();

    list.prependItem({ id: 9 });
    await flushPromises();

    expect(list.page.value).toBe(1);
    expect(list.items.value).toEqual(rows(1, 2));
    expect(fetcher).toHaveBeenLastCalledWith({ page: 1, per_page: 2 });
  });
});
