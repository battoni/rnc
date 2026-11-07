import { beforeEach, describe, expect, it, vi } from 'vitest';

/*
 * What a caller gets back when the app calls its own request off.
 *
 * The route guard aborts everything in flight on every navigation. Rejecting for that ran the
 * view's `.catch` on the way out of the page — a logout during Home's own loads put "Something
 * went wrong" on the login screen — and resolving would be worse, because the view would render
 * the empty answer as an answer. So the promise simply never settles: there is no honest thing to
 * tell a caller who is already gone.
 *
 * The busy counter is the other half. It is paid back BEFORE that promise is handed over, or the
 * global progress bar would run for the rest of the session.
 */

const { axios, endRequest } = vi.hoisted(() => ({ axios: vi.fn(), endRequest: vi.fn() }));

vi.mock('@Libraries', () => ({ axios }));
vi.mock('@Libraries/i18n', () => ({ default: { global: { locale: { value: 'pt-BR' } } } }));
vi.mock('@Composables', () => ({
  useAppBusy: () => ({ beginRequest: vi.fn(), endRequest }),
  useGlobalAbortController: () => ({ getSignal: () => undefined }),
}));

const { default: API } = await import('./provider');

/** Resolves to `'pending'` when the promise under test has not settled by the deadline. */
function settledWithin(promise: Promise<unknown>, ms: number) {
  return Promise.race([
    promise.then(
      () => 'resolved',
      () => 'rejected'
    ),
    new Promise((resolve) => setTimeout(() => resolve('pending'), ms)),
  ]);
}

describe('API provider — a request that was called off', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('never settles, so no view can answer for a page that has gone', async () => {
    axios.mockRejectedValueOnce({ code: 'ERR_CANCELED', message: 'canceled' });

    const OUTCOME = await settledWithin(API({ endpoint: 'news', method: 'get' }), 50);

    expect(OUTCOME).toBe('pending');
  });

  it('pays the busy counter back anyway', async () => {
    axios.mockRejectedValueOnce({ code: 'ERR_CANCELED', message: 'canceled' });

    await settledWithin(API({ endpoint: 'news', method: 'get' }), 50);

    expect(endRequest).toHaveBeenCalledTimes(1);
  });

  it('still rejects a real failure, and still pays the counter back', async () => {
    axios.mockRejectedValueOnce({ response: { status: 500 }, message: 'boom' });

    await expect(API({ endpoint: 'news', method: 'get' })).rejects.toBeDefined();
    expect(endRequest).toHaveBeenCalledTimes(1);
  });

  it('hands back the body of a request that worked', async () => {
    axios.mockResolvedValueOnce({ data: { data: [{ id: '1' }] } });

    await expect(API({ endpoint: 'news', method: 'get' })).resolves.toEqual({ data: [{ id: '1' }] });
    expect(endRequest).toHaveBeenCalledTimes(1);
  });
});

describe('API provider — query', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    axios.mockResolvedValue({ data: {} });
  });

  function requestedUrl() {
    return String(axios.mock.calls[0]?.[0]);
  }

  it('builds the query string, so no service concatenates one by hand', async () => {
    await API({ endpoint: 'auth/users', method: 'get', query: { order_by: 'name_asc', page: 2 } });

    expect(requestedUrl()).toMatch(/\/auth\/users\?order_by=name_asc&page=2$/);
  });

  it('drops empty values instead of sending them as a filter for the empty string', async () => {
    await API({
      endpoint: 'auth/users',
      method: 'get',
      query: { page: 1, profile_type: '', status: undefined, x: null },
    });

    expect(requestedUrl()).toMatch(/\/auth\/users\?page=1$/);
  });

  it('sends a list the way Laravel reads one', async () => {
    await API({ endpoint: 'auth/users', method: 'get', query: { ids: [3, 7] } });

    expect(decodeURIComponent(requestedUrl())).toMatch(/\/auth\/users\?ids\[\]=3&ids\[\]=7$/);
  });

  // Laravel's `boolean` rule accepts 1/0 and refuses the string "true": `?mine=true` is a 422.
  it('sends a boolean as 1 or 0, the spelling Laravel validates', async () => {
    await API({ endpoint: 'ideas', method: 'get', query: { archived: false, flags: [true, false], mine: true } });

    expect(decodeURIComponent(requestedUrl())).toMatch(/\/ideas\?archived=0&flags\[\]=1&flags\[\]=0&mine=1$/);
  });

  it('leaves the endpoint alone when there is no query', async () => {
    await API({ endpoint: 'auth/users', method: 'get', query: { page: undefined } });

    expect(requestedUrl()).toMatch(/\/auth\/users$/);
  });
});

describe('API provider — headers a service adds', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // A service sends a header of its own — a token for a public link, say — without reaching past
  // the provider into axios. Merged over the defaults, so the content type is still there.
  it('merges the extra headers over the defaults', async () => {
    axios.mockResolvedValueOnce({ data: { data: [] } });

    await API({ endpoint: 'items/abc', method: 'get', headers: { 'X-Access-Token': 'secret' } });

    expect(axios.mock.calls[0]?.[1]?.headers).toEqual({
      'Accept-Language': 'pt-BR',
      'Content-Type': 'application/json',
      'X-Access-Token': 'secret',
    });
  });

  it('sends the language the interface is in', async () => {
    axios.mockResolvedValueOnce({ data: { data: [] } });

    await API({ endpoint: 'me', method: 'get' });

    expect(axios.mock.calls[0]?.[1]?.headers['Accept-Language']).toBe('pt-BR');
  });
});
