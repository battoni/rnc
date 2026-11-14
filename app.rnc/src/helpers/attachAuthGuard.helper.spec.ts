import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import attachAuthGuard from './attachAuthGuard.helper';
import { getLoginPath } from './i18n/getLoginPath.helper';

// Mock the user store so the guard can be exercised without a real session.
const fetchMe = vi.fn();
const userStoreState: { user: unknown; hasSessionHint: boolean; modules: string[]; permissions: string[] } = {
  user: null,
  hasSessionHint: true,
  modules: [],
  permissions: [],
};

const { cancelAllRequests, createNewController } = vi.hoisted(() => ({
  cancelAllRequests: vi.fn(),
  createNewController: vi.fn(),
}));

vi.mock('@UserModule', () => ({
  useUserStore: () => ({
    get user() {
      return userStoreState.user;
    },
    fetchMe,
    hasSessionHint: () => userStoreState.hasSessionHint,
    hasModule: (key: string) => userStoreState.modules.includes(key),
    hasPermission: (key: string) => userStoreState.permissions.includes(key),
  }),
}));

vi.mock('@Composables', () => ({
  useGlobalAbortController: () => ({ cancelAllRequests, createNewController }),
}));

type Guard = (
  to: {
    name?: string;
    meta: Record<string, unknown>;
    fullPath?: string;
    hash?: string;
    path?: string;
    redirectedFrom?: { meta: Record<string, unknown> };
    query?: Record<string, string | string[] | null>;
  },
  from: { name?: string },
  next: ReturnType<typeof vi.fn>
) => unknown;

// Asked, not spelled out. The guard resolves the login path from the active locale, so a literal
// here would assert the harness's language rather than the guard's behaviour — and would go red
// the day the test locale changed, for a reason that has nothing to do with the guard.
const LOGIN_REDIRECT = { path: getLoginPath() };

function makeRouterCapture() {
  let guard: Guard | undefined;
  const router = {
    beforeEach: (fn: Guard) => {
      guard = fn;
    },
  };
  attachAuthGuard(router as never);
  if (!guard) throw new Error('guard was not registered');
  return guard;
}

describe('attachAuthGuard', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    fetchMe.mockReset();
    cancelAllRequests.mockClear();
    createNewController.mockClear();
    userStoreState.user = null;
    userStoreState.hasSessionHint = true;
    userStoreState.modules = [];
    userStoreState.permissions = [];
  });

  it('allows a public route through without checking the user', () => {
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'login', meta: { isPublic: true } }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith();
    expect(fetchMe).not.toHaveBeenCalled();
  });

  it('redirects an authenticated user away from a public route to home', () => {
    userStoreState.user = { id: 1 };
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'login', meta: { isPublic: true } }, { name: 'users' }, next);

    expect(next).toHaveBeenCalledWith({ name: 'home' });
  });

  it('lets an open route through for a signed-in person, without sending them home', () => {
    userStoreState.user = { id: 1 };
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'not-found', meta: { isOpen: true }, fullPath: '/typo' }, { name: 'users' }, next);

    expect(next).toHaveBeenCalledWith();
  });

  it('lets an open route through for a signed-out visitor, without a login detour or a fetch', () => {
    userStoreState.hasSessionHint = false;
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'not-found', meta: { isOpen: true }, fullPath: '/typo' }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith();
    expect(fetchMe).not.toHaveBeenCalled();
  });

  it('allows a protected route when a user is already loaded', () => {
    userStoreState.user = { id: 1 };
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'users', meta: {} }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith();
    expect(fetchMe).not.toHaveBeenCalled();
  });

  /*
   * What a route costs is declared on the route, and this is the only place that charges it.
   *
   * The routes this bootstrap ships already carry `meta.permission`, and their own comments say
   * the declaration closes the route while the nav filter only hides it. Without the check here,
   * the declaration closed nothing: the nav hid the link and typing the address opened the board
   * anyway. A promise in metadata that nothing keeps is worse than no promise, because it stops
   * the next reader from going to look for the guard.
   */
  it('turns an authenticated user away from a route they cannot afford', () => {
    userStoreState.user = { id: 1 };
    userStoreState.permissions = ['users.read'];
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'ideas', meta: { permission: 'ideas.read' } }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith({ name: 'home' });
  });

  it('lets them through when they hold the permission the route declares', () => {
    userStoreState.user = { id: 1 };
    userStoreState.permissions = ['ideas.read'];
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'ideas', meta: { permission: 'ideas.read' } }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith();
  });

  /*
   * rnc-4 verify (API batch): role writes are the operator's alone on the API (403 `platform.operatorOnly`), and
   * a key can be granted to a firm role. A route that declares `meta.role` answers only to that role — the key
   * alone does not open it.
   */
  it('turns away a person holding the key but not the role the route declares', () => {
    userStoreState.user = { id: 7, role_slug: 'gestao' };
    userStoreState.permissions = ['permissions.read', 'permissions.update'];
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'roles', meta: { permission: 'permissions.read', role: 'operador' } }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith({ name: 'home' });
  });

  it('lets the declared role through with the key', () => {
    userStoreState.user = { id: 1, role_slug: 'operador' };
    userStoreState.permissions = ['permissions.read'];
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'roles', meta: { permission: 'permissions.read', role: 'operador' } }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith();
  });

  it('charges the same permission after the session is fetched, not only before', async () => {
    fetchMe.mockImplementation(() => {
      userStoreState.user = { id: 1 };

      return Promise.resolve(undefined);
    });
    const guard = makeRouterCapture();
    const next = vi.fn();

    await guard({ name: 'ideas', meta: { permission: 'ideas.read' } }, { name: 'home' }, next);

    expect(fetchMe).toHaveBeenCalledOnce();
    expect(next).toHaveBeenCalledWith({ name: 'home' });
  });

  it('fetches the user on a protected route, then proceeds on success', async () => {
    fetchMe.mockResolvedValue(undefined);
    const guard = makeRouterCapture();
    const next = vi.fn();

    await guard({ name: 'users', meta: {} }, { name: 'home' }, next);

    expect(fetchMe).toHaveBeenCalledOnce();
    expect(next).toHaveBeenCalledWith();
  });

  it('redirects to login when fetching the user fails', async () => {
    fetchMe.mockRejectedValue(new Error('401'));
    const guard = makeRouterCapture();
    const next = vi.fn();

    await guard({ name: 'users', meta: {} }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith(LOGIN_REDIRECT);
  });

  it('skips the fetch and redirects to login when no session hint exists', () => {
    userStoreState.hasSessionHint = false;
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'users', meta: {} }, { name: 'home' }, next);

    expect(fetchMe).not.toHaveBeenCalled();
    expect(next).toHaveBeenCalledWith(LOGIN_REDIRECT);
  });

  /*
   * The destination has to survive the trip to the login screen.
   *
   * Without it, someone who follows a link to an internal screen signs in and lands on Home — the
   * app technically let them in and practically lost them, because they have to find from a list
   * the thing they were sent to look at.
   */
  it('carries where the person was going when it sends them to login', () => {
    userStoreState.hasSessionHint = false;
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'users', meta: {}, fullPath: '/users?page=2' }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith({
      path: getLoginPath(),
      query: { redirect: '/users?page=2' },
    });
  });

  it('carries it on the other exit too, when the session turns out to be dead', async () => {
    fetchMe.mockRejectedValue(new Error('401'));
    const guard = makeRouterCapture();
    const next = vi.fn();

    await guard({ name: 'users', meta: {}, fullPath: '/users' }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith({ path: getLoginPath(), query: { redirect: '/users' } });
  });

  /*
   * Home and the login screen itself are dropped rather than remembered: sending someone to
   * login and then back to the page they would have landed on anyway adds a parameter to the URL
   * and changes nothing. A query key holding a pointless value is worse than no query at all.
   */
  it('does not carry home, nor the login screen itself', () => {
    userStoreState.hasSessionHint = false;
    const guard = makeRouterCapture();

    for (const fullPath of ['/', getLoginPath()]) {
      const next = vi.fn();

      guard({ name: 'users', meta: {}, fullPath }, { name: 'home' }, next);

      expect(next, fullPath).toHaveBeenCalledWith(LOGIN_REDIRECT);
    }
  });

  /*
   * The other half: an authenticated person who lands on a public route goes where they were
   * going, not always home. That is what closes the loop after they sign in — and it is the same
   * `intendedPathFrom`, so the hostile value is refused here too.
   */
  it('sends an already-authenticated visitor to the destination they carried', () => {
    userStoreState.user = { id: 1 };
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'auth.login', meta: { isPublic: true }, query: { redirect: '/ideas' } }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith('/ideas');
  });

  it('refuses a destination that names another host, and falls back home', () => {
    userStoreState.user = { id: 1 };
    const guard = makeRouterCapture();

    for (const hostile of ['//evil.test', 'https://evil.test/users']) {
      const next = vi.fn();

      guard({ name: 'auth.login', meta: { isPublic: true }, query: { redirect: hostile } }, { name: 'home' }, next);

      expect(next, hostile).toHaveBeenCalledWith({ name: 'home' });
    }
  });

  it('cancels in-flight requests when navigating to a different route', () => {
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'login', meta: { isPublic: true } }, { name: 'home' }, next);

    expect(cancelAllRequests).toHaveBeenCalledOnce();
    expect(createNewController).toHaveBeenCalledOnce();
  });

  it('does not cancel requests when navigating within the same route', () => {
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'login', meta: { isPublic: true } }, { name: 'login' }, next);

    expect(cancelAllRequests).not.toHaveBeenCalled();
  });
  /*
   * rnc-4 R17, A24: a screen of a module the firm has off is not found — the not-found view at the
   * same address, not Home — however the person got there (typed, bookmarked, an old link). The rail
   * hides the entry; this is what closes the route, as `meta.permission` does for a key.
   */
  it("shows not-found at the same address when the route's module is off for the firm (rnc-4 A24)", () => {
    userStoreState.user = { id: 1 };
    userStoreState.permissions = ['partners.manage'];
    userStoreState.modules = ['briefing'];
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard(
      {
        name: 'partners-pt-BR',
        meta: { module: 'partners', permission: 'partners.manage' },
        path: '/gestao/parceiros',
        query: { page: '2' },
        hash: '',
      },
      { name: 'home' },
      next
    );

    expect(next).toHaveBeenCalledWith({
      name: 'not-found',
      params: { pathMatch: ['gestao', 'parceiros'] },
      query: { page: '2' },
      hash: '',
    });
  });

  it('lets the route through when the firm has its module on', () => {
    userStoreState.user = { id: 1 };
    userStoreState.permissions = ['partners.manage'];
    userStoreState.modules = ['partners'];
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard({ name: 'partners-pt-BR', meta: { module: 'partners' }, path: '/gestao/parceiros' }, { name: 'home' }, next);

    expect(next).toHaveBeenCalledWith();
  });

  it('charges the module after the session is fetched too (a hard navigation)', async () => {
    fetchMe.mockImplementation(() => {
      userStoreState.user = { id: 1 };

      return Promise.resolve(undefined);
    });
    const guard = makeRouterCapture();
    const next = vi.fn();

    await guard({ name: 'radar-pt-BR', meta: { module: 'radar' }, path: '/gestao/radar' }, { name: undefined }, next);

    expect(next).toHaveBeenCalledWith(expect.objectContaining({ name: 'not-found' }));
  });

  /*
   * A landing that redirects into an opt-in screen (`/gestao` → the briefing) names where to go when
   * that screen's module is off: the Gestão link must open something, not "page not found".
   */
  it('sends a landing redirect to its fallback when the screen it redirected to is off', () => {
    userStoreState.user = { id: 1 };
    const guard = makeRouterCapture();
    const next = vi.fn();

    guard(
      {
        name: 'briefing-pt-BR',
        meta: { module: 'briefing' },
        path: '/gestao/briefing',
        redirectedFrom: { meta: { moduleFallback: '/gestao/metricas' } },
      },
      { name: 'home' },
      next
    );

    expect(next).toHaveBeenCalledWith('/gestao/metricas');
  });
});
