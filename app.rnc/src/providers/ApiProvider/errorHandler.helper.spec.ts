import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getLoginPath } from '@Helpers';
import { useUserStore } from '@UserModule';
import errorHandler from './errorHandler.helper';

/*
 * The router is imported dynamically inside the handler, to stay out of the guard's own cycle —
 * so it is mocked by module path here rather than injected.
 */
const { push, currentRoute } = vi.hoisted(() => ({
  push: vi.fn(),
  currentRoute: { value: { fullPath: '/users', meta: {} } as { fullPath: string; meta: Record<string, unknown> } },
}));

vi.mock('@Libraries/router', () => ({ default: { push, currentRoute } }));

const SIGNED_IN = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'administrator',
};

function apiError(status: number) {
  return { response: { status, data: { message: 'errors.unexpected' } }, message: 'boom' };
}

async function handle(error: unknown) {
  // The handler always rethrows — it decorates and passes on. Swallowing here keeps each test
  // about the session, which is the only thing these cases are asserting.
  await expect(errorHandler(error)).rejects.toBeDefined();
}

/*
 * Which message reaches the screen.
 *
 * `ApiResponse::error()` writes the sentence into `data.error`, and every named helper in api.rnc
 * routes through it — `notFound` alone is 28 call sites. The handler read `data.message`, which
 * those responses do not have, so the API's own words were dropped and the person saw axios's
 * "Request failed with status code 404".
 */
describe('errorHandler — which message reaches the screen', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  async function messageOf(response: Record<string, unknown>, status = 400) {
    const error = { response: { status, data: response }, message: 'Request failed' } as {
      response: unknown;
      message: string;
      translatedMessage?: string;
    };

    await expect(errorHandler(error)).rejects.toBeDefined();

    return error.translatedMessage;
  }

  it('reads the envelope message from data.error', async () => {
    // A key that EXISTS, on purpose: `translateError` resolves it, and an unknown key resolves to
    // the generic sentence — which is exactly what the bug produced, so asserting against a raw
    // key would pass whether the envelope was read or not.
    const message = await messageOf({ data: null, error: 'errors.somethingWentWrong', status_code: 404 }, 404);

    expect(message).toBe('Something went wrong.');
  });

  it('reads it at every status the named helpers produce, not only 422', async () => {
    // badRequest 400 · unauthorized 401 · forbidden 403 · notFound 404 · conflict 409 ·
    // internalError 500 · serviceUnavailable 503. None of them is 422.
    for (const status of [400, 401, 403, 404, 409, 500, 503]) {
      expect(
        await messageOf({ data: null, error: 'errors.somethingWentWrong', status_code: status }, status),
        `status ${status}`
      ).toBe('Something went wrong.');
    }
  });

  it('still prefers the validation bag, which is a different shape entirely', async () => {
    const message = await messageOf({ errors: { username: ['auth.login.invalidCredentials'] } }, 422);

    expect(message).toBe('The username or password is incorrect');
  });

  it('falls through to data.message when there is no envelope error', async () => {
    expect(await messageOf({ message: 'errors.somethingWentWrong' })).toBe('Something went wrong.');
  });

  /*
   * Laravel's throttle reply is English prose, not a key. Without a status branch it became
   * `errors.unexpected` with "Too Many Attempts." interpolated — the raw toast on rapid applause.
   */
  it("maps 429 to a friendly sentence instead of Laravel's throttle prose", async () => {
    expect(await messageOf({ message: 'Too Many Attempts.' }, 429)).toBe('Please wait a moment and try again.');
  });

  it('ignores a non-string error, rather than rendering an object', async () => {
    // `extraData` can merge anything into the envelope; a caller passing a bag under `error`
    // would otherwise reach `translateError` as "[object Object]".
    expect(await messageOf({ error: { field: 'x' }, message: 'errors.somethingWentWrong' })).toBe(
      'Something went wrong.'
    );
  });
});

describe('errorHandler — which status ends a session', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  it('ends the session on 401, because the server no longer knows who you are', async () => {
    const userStore = useUserStore();
    userStore.setUserAndPermissions(SIGNED_IN as never, ['users.read']);

    await handle(apiError(401));

    expect(userStore.user).toBeNull();
  });

  /*
   * The case this file exists for. A 403 means the server knows exactly who you are and this is
   * not yours: the session is fine. Ending it turns "you cannot open this page" into "you have
   * been signed out", which is a worse answer to a smaller problem — and it fired for anyone who
   * touched a screen their role does not reach.
   */
  it('leaves the session alone on 403, because you are signed in and simply not allowed', async () => {
    const userStore = useUserStore();
    userStore.setUserAndPermissions(SIGNED_IN as never, ['users.read']);

    await handle(apiError(403));

    expect(userStore.user).not.toBeNull();
    expect(userStore.permissions).toEqual(['users.read']);
  });

  it('leaves the session alone on every other status', async () => {
    const userStore = useUserStore();

    for (const status of [400, 404, 422, 429, 500]) {
      userStore.setUserAndPermissions(SIGNED_IN as never, []);

      await handle(apiError(status));

      expect(userStore.user, `status ${status} ended the session`).not.toBeNull();
    }
  });
});

/*
 * A request the app itself called off.
 *
 * The route guard aborts everything in flight on every navigation, so this is the ordinary end of
 * any request the person outran — most visibly on logout, where Home's own loads were still open.
 * Treated as a failure it printed an API error to the console and put "Something went wrong" on
 * the login screen the person had just arrived at.
 */
describe('errorHandler — a request the app called off', () => {
  // Held as a handle rather than read back off `console`, which the lint rule bans in source.
  let consoleError: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    setActivePinia(createPinia());
    consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
    // `spyOn` over an existing spy hands back the same mock, history included — and the describe
    // above it has already filled that history. Without this the "said nothing" assertions read
    // the previous block's output.
    vi.clearAllMocks();
  });

  const CALL_OFFS = [
    { name: 'axios cancellation', error: { code: 'ERR_CANCELED', message: 'canceled' } },
    { name: 'a signal aborted before the send', error: { name: 'AbortError', message: 'signal is aborted' } },
  ];

  it.each(CALL_OFFS)('says nothing to the console about $name', async ({ error }) => {
    await expect(errorHandler(error)).rejects.toBeDefined();

    expect(consoleError).not.toHaveBeenCalled();
  });

  it('does not end the session over one', async () => {
    const userStore = useUserStore();
    userStore.setUserAndPermissions(SIGNED_IN as never, ['users.read']);

    await expect(errorHandler({ code: 'ERR_CANCELED', message: 'canceled' })).rejects.toBeDefined();

    expect(userStore.user).not.toBeNull();
  });

  it('does not dress one up with a sentence for a view to show', async () => {
    const error = { code: 'ERR_CANCELED', message: 'canceled' } as { code: string; translatedMessage?: string };

    await expect(errorHandler(error)).rejects.toBeDefined();

    expect(error.translatedMessage).toBeUndefined();
  });

  it('still reports a real failure to the console', async () => {
    await handle(apiError(500));

    expect(consoleError).toHaveBeenCalled();
  });
});

/*
 * A session that ended has to end on screen too.
 *
 * Clearing the store alone left the page drawn from data the server no longer recognises, so a
 * form could be filled in and submitted against a session that was already over. Where the person
 * was travels along: signing back in returns them there instead of to Home.
 */
describe('errorHandler — where a 401 leaves you', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.clearAllMocks();
    currentRoute.value = { fullPath: '/users', meta: {} };
  });

  it('sends you to the login screen carrying where you were', async () => {
    await handle(apiError(401));

    expect(push).toHaveBeenCalledWith({ path: getLoginPath(), query: { redirect: '/users' } });
  });

  it('carries nothing when there is nothing worth carrying', async () => {
    currentRoute.value = { fullPath: '/', meta: {} };

    await handle(apiError(401));

    expect(push).toHaveBeenCalledWith({ path: getLoginPath(), query: undefined });
  });

  /* A public page never needed the session, and pushing would replace a half-typed form. */
  it('leaves a public page where it is', async () => {
    currentRoute.value = { fullPath: '/forgot-password', meta: { isPublic: true } };

    await handle(apiError(401));

    expect(push).not.toHaveBeenCalled();
  });

  it('does not eject on a 403, which is a smaller problem than being signed out', async () => {
    await handle(apiError(403));

    expect(push).not.toHaveBeenCalled();
  });
});

/*
 * rnc-4 R5, A7: 403 `firms.mismatch` is the one 403 that ends the session here. The session belongs
 * to another firm than the host's — a cookie that should never have ridden to this host — so the
 * screen it draws is someone else's firm, and the honest answer is the login screen of THIS one.
 */
describe('errorHandler — a session from another firm', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.clearAllMocks();
    currentRoute.value = { fullPath: '/leads', meta: {} };
  });

  function mismatch() {
    return { response: { status: 403, data: { data: null, error: 'firms.mismatch', status_code: 403 } }, message: 'x' };
  }

  it('clears the session store', async () => {
    const userStore = useUserStore();
    userStore.setUserAndPermissions(SIGNED_IN as never, ['leads.read']);

    await handle(mismatch());

    expect(userStore.user).toBeNull();
  });

  it('goes to the login screen', async () => {
    await handle(mismatch());

    expect(push).toHaveBeenCalledWith({ path: getLoginPath(), query: { redirect: '/leads' } });
  });
});
