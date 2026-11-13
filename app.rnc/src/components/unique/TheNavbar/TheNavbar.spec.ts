import { computed } from 'vue';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type * as AuthModule from '@AuthModule';
import type { Router } from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import { NAV_EXPANDED_GROUPS_STORAGE_KEY } from '@Constants';
import useUiStore from '@Stores';

const { logoutService, resetAllStores } = vi.hoisted(() => ({ logoutService: vi.fn(), resetAllStores: vi.fn() }));

// The barrel is already aliased to a test stub; this only swaps the reset for a spy so the
// "does the session actually end" contract can be asserted rather than assumed.
vi.mock('@Libraries', async (importActual) => ({
  ...(await importActual<Record<string, unknown>>()),
  resetAllStores,
}));

vi.mock('@AuthModule', async (importActual) => {
  const actual = await importActual<typeof AuthModule>();

  return { ...actual, LogoutService: logoutService };
});

// The real local items are a product decision in motion (and store-backed); the spec pins one
// of each declared shape instead — an expander (children, no route), a link-with-toggle
// (children + route), and a store-shaped group whose children carry params and literal labels.
vi.mock('./navItems.local', () => ({
  useLocalNavItems: () =>
    computed(() => [
      {
        icon: 'pi pi-compass',
        label: 'navigation.workspace',
        children: [
          { icon: 'pi pi-inbox', label: 'navigation.inbox', permission: 'inbox.read', routeName: 'inbox' },
          { icon: 'pi pi-list-check', label: 'navigation.week', permission: 'week.read', routeName: 'week' },
          // `users` IS on the bottom bar's allow-list, and here it exists only as a CHILD.
          // That is the whole point of the flatness test below.
          {
            icon: 'pi pi-users',
            key: 'nested-users',
            label: 'navigation.users',
            permission: 'inbox.read',
            routeName: 'users',
          },
        ],
      },
      {
        icon: 'pi pi-flag',
        label: 'navigation.reports',
        permission: 'inbox.read',
        routeName: 'reports',
        children: [
          { icon: 'pi pi-chart-line', label: 'navigation.trends', permission: 'inbox.read', routeName: 'trends' },
        ],
      },
      {
        icon: 'pi pi-compass',
        label: 'navigation.projects',
        permission: 'inbox.read',
        routeName: 'projects',
        children: [
          {
            icon: 'pi pi-heart',
            isLabelLiteral: true,
            key: 'project-7',
            label: 'Minha lista',
            params: { id: '7' },
            permission: 'inbox.read',
            routeName: 'project',
          },
        ],
      },
    ]),
  localNavSettings: [],
}));

const { useUserStore } = await import('@UserModule');
const { default: TheNavbar } = await import('./TheNavbar.vue');

const desktopStub = {
  name: 'ONavbarDesktop',
  props: ['expandedGroupKeys', 'itemsWithRoutes', 'personalizationWithRoute', 'settingsWithRoutes'],
  emits: ['logout', 'toggle-group'],
  template: '<div class="desktop-stub" />',
};

const drawerStub = {
  name: 'ONavbarDrawer',
  props: [
    'drawerClass',
    'drawerPosition',
    'expandedGroupKeys',
    'itemsWithRoutes',
    'personalizationWithRoute',
    'settingsWithRoutes',
    'visible',
  ],
  emits: ['close', 'logout', 'toggle-group', 'update:visible'],
  template: '<div class="drawer-stub" />',
};

const mobileStub = {
  name: 'ONavbarMobile',
  props: ['mobileItems', 'personalizationWithRoute'],
  emits: ['toggle-drawer'],
  template: '<div class="mobile-stub" />',
};

const globalStubs = {
  ONavbarDesktop: desktopStub,
  ONavbarDrawer: drawerStub,
  ONavbarMobile: mobileStub,
};

const SUPERADMIN = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'administrator',
  permissions: [],
};

const PLAIN_USER = { ...SUPERADMIN, role_slug: 'user' };

let router: Router;

function blankRoute(name: string, path: string) {
  return { name, path, component: { template: '<div />' } };
}

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      blankRoute('home', '/'),
      blankRoute('login', '/login'),
      // getI18nRouteName appends the locale suffix when neither the localized nor the base
      // name is registered on the app router, which is what the test router stub gives it.
      blankRoute('inbox-en', '/inbox'),
      blankRoute('week-en', '/week'),
      blankRoute('reports-en', '/reports'),
      blankRoute('trends-en', '/trends'),
      blankRoute('projects-en', '/projects'),
      blankRoute('project-en', '/projects/:id'),
      blankRoute('team-home-en', '/team'),
      blankRoute('ceo-home-en', '/ceo'),
      blankRoute('operator-home-en', '/operator'),
      blankRoute('users-en', '/users'),
      blankRoute('roles-and-permissions-en', '/roles'),
      blankRoute('personalization-en', '/personalization'),
    ],
  });
}

let viewportListeners: Array<(event: MediaQueryListEvent) => void> = [];

/**
 * The navbar reads the viewport through `useViewport`, which is a media query rather than a
 * width plus a resize listener — so a narrow screen is stated here, not measured.
 */
function setViewportWidth(isMobile: boolean) {
  viewportListeners = [];

  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    writable: true,
    value: (query: string) => ({
      matches: query.includes('max-width') ? isMobile : false,
      media: query,
      addEventListener: (_name: string, listener: (event: MediaQueryListEvent) => void) =>
        viewportListeners.push(listener),
      removeEventListener: (_name: string, listener: (event: MediaQueryListEvent) => void) => {
        viewportListeners = viewportListeners.filter((candidate) => candidate !== listener);
      },
      addListener: () => {},
      removeListener: () => {},
      dispatchEvent: () => false,
      onchange: null,
    }),
  });
}

function crossTheBreakpoint(isMobile: boolean) {
  viewportListeners.forEach((listener) => listener({ matches: isMobile } as MediaQueryListEvent));
}

function mountNavbar() {
  const wrapper = mountWithPlugins(TheNavbar, { global: { plugins: [router], stubs: globalStubs } });

  // mountWithPlugins installs its own Pinia, so the stores must be read after mounting or
  // the assertions inspect different instances from the ones the component holds.
  return { uiStore: useUiStore(), userStore: useUserStore(), wrapper };
}

function labelsOf(items: { label: string }[]) {
  return items.map((item) => item.label);
}

beforeEach(async () => {
  localStorage.clear();
  logoutService.mockReset();
  logoutService.mockResolvedValue({});
  setActivePinia(createPinia());
  setViewportWidth(false);

  router = buildRouter();
  router.push('/');
  await router.isReady();
});

describe('TheNavbar — variants', () => {
  it('mounts all three variants, leaving the width to each one CSS', () => {
    const { wrapper } = mountNavbar();

    expect(wrapper.find('.desktop-stub').exists()).toBe(true);
    expect(wrapper.find('.drawer-stub').exists()).toBe(true);
    expect(wrapper.find('.mobile-stub').exists()).toBe(true);
  });

  it('collapses the rail to a zero track instead of unmounting it', async () => {
    const { uiStore, wrapper } = mountNavbar();

    expect(wrapper.classes()).toContain('grid-cols-[1fr]');

    uiStore.isNavCollapsed = true;
    await wrapper.vm.$nextTick();

    // Unmounting jumped the page sideways; the track animates instead, so the rail stays in the
    // DOM. `inert` is what takes it out of reach. The drawer stays reachable from the page header.
    expect(wrapper.classes()).toContain('grid-cols-[0fr]');
    expect(wrapper.find('.desktop-stub').exists()).toBe(true);
    expect(wrapper.find('[inert] .desktop-stub').exists()).toBe(true);
    expect(wrapper.find('.drawer-stub').exists()).toBe(true);
  });

  it('leaves the expanded rail reachable', () => {
    const { wrapper } = mountNavbar();

    expect(wrapper.find('[inert]').exists()).toBe(false);
  });

  it('docks the drawer to the left at tablet width', () => {
    const { wrapper } = mountNavbar();

    expect(wrapper.findComponent(drawerStub).props('drawerPosition')).toBe('left');
    expect(wrapper.findComponent(drawerStub).props('drawerClass')).toBe('w-54');
  });

  it('takes the whole screen for the drawer on a phone', async () => {
    setViewportWidth(true);

    const { wrapper } = mountNavbar();
    await wrapper.vm.$nextTick();

    expect(wrapper.findComponent(drawerStub).props('drawerPosition')).toBe('full');
    expect(wrapper.findComponent(drawerStub).props('drawerClass')).toBe('nav-drawer-mobile');
  });

  it('follows the viewport across the breakpoint while mounted', async () => {
    const { wrapper } = mountNavbar();

    crossTheBreakpoint(true);
    await wrapper.vm.$nextTick();

    expect(wrapper.findComponent(drawerStub).props('drawerPosition')).toBe('full');
  });

  it('stops listening once unmounted', () => {
    const { wrapper } = mountNavbar();

    wrapper.unmount();

    expect(viewportListeners).toHaveLength(0);
  });
});

describe('TheNavbar — permissions and roles', () => {
  it('hides an item whose permission the account does not hold', () => {
    const { wrapper } = mountNavbar();

    // Home declares no permission — it is the bootstrap's only ungated screen — so what an
    // empty account sees is exactly that and nothing the local layer added.
    expect(labelsOf(wrapper.findComponent(desktopStub).props('itemsWithRoutes'))).toEqual(['navigation.home']);
  });

  it('shows an item once its permission is granted', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(PLAIN_USER as never, ['inbox.read']);
    await wrapper.vm.$nextTick();

    expect(labelsOf(wrapper.findComponent(desktopStub).props('itemsWithRoutes'))).toContain('navigation.reports');
  });

  it('hides every settings entry from an account with no permissions and no role', () => {
    const { wrapper } = mountNavbar();

    expect(wrapper.findComponent(desktopStub).props('settingsWithRoutes')).toEqual([]);
  });

  it('gates the admin settings behind their permission', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(PLAIN_USER as never, ['users.read']);
    await wrapper.vm.$nextTick();

    const labels = labelsOf(wrapper.findComponent(desktopStub).props('settingsWithRoutes'));

    expect(labels).toContain('navigation.users');
    expect(labels).not.toContain('navigation.roles');
  });

  it('resolves the localized path for every item but home', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(PLAIN_USER as never, ['inbox.read']);
    await wrapper.vm.$nextTick();

    const items = wrapper.findComponent(desktopStub).props('itemsWithRoutes');
    const reports = items.find((item: { label: string }) => item.label === 'navigation.reports');

    // Home stays a name — it has no localized twin to resolve. A linked item resolves to a path.
    // By label rather than by index: the local layer's expander sits between them and carries no
    // route of its own, so an index here would be asserting the fixture's shape, not the behaviour.
    expect(items[0].route).toEqual({ name: 'home' });
    expect(reports.route).toBe('/reports');
  });

  it('resolves the personalization route for every variant', () => {
    const { wrapper } = mountNavbar();

    expect(wrapper.findComponent(desktopStub).props('personalizationWithRoute').route).toBe('/personalization');
    expect(wrapper.findComponent(mobileStub).props('personalizationWithRoute').route).toBe('/personalization');
  });

  it('narrows the bottom bar to the primary routes the account can reach', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(SUPERADMIN as never, ['users.read']);
    await wrapper.vm.$nextTick();

    // Settings-only routes never reach the bottom bar, even for a superadmin — `users` is on the
    // allow-list and still does not appear, because it is a settings entry rather than a top-level one.
    expect(labelsOf(wrapper.findComponent(mobileStub).props('mobileItems'))).toEqual(['navigation.home']);
  });
});

describe('TheNavbar — groups', () => {
  function itemByLabel(wrapper: ReturnType<typeof mountNavbar>['wrapper'], label: string) {
    return wrapper
      .findComponent(desktopStub)
      .props('itemsWithRoutes')
      .find((item: { label: string }) => item.label === label);
  }

  it('drops an expander whose children were all filtered out', () => {
    const { wrapper } = mountNavbar();

    // Home is the only ungated core screen; the local expander's children all require a
    // permission this account does not hold, so the expander itself never renders.
    expect(labelsOf(wrapper.findComponent(desktopStub).props('itemsWithRoutes'))).toEqual(['navigation.home']);
  });

  it('reveals an expander with only the children the account can reach', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(PLAIN_USER as never, ['inbox.read']);
    await wrapper.vm.$nextTick();

    const workspace = itemByLabel(wrapper, 'navigation.workspace');

    // `week.read` was not granted, so that child is dropped; the expander itself has no route.
    expect(workspace.route).toBeUndefined();
    expect(workspace.key).toBe('navigation.workspace');
    expect(workspace.children).toEqual([
      { icon: 'pi pi-inbox', key: 'inbox', label: 'navigation.inbox', route: '/inbox', routeName: 'inbox' },
      { icon: 'pi pi-users', key: 'nested-users', label: 'navigation.users', route: '/users', routeName: 'users' },
    ]);
  });

  it('keeps both the route and the children on a link-with-toggle parent', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(PLAIN_USER as never, ['inbox.read']);
    await wrapper.vm.$nextTick();

    const reports = itemByLabel(wrapper, 'navigation.reports');

    expect(reports.route).toBe('/reports');
    expect(reports.key).toBe('reports');
    expect(labelsOf(reports.children)).toEqual(['navigation.trends']);
  });

  it('keeps the bottom bar flat — a nested child never surfaces there', async () => {
    const { userStore, wrapper } = mountNavbar();

    // `users` is on the bottom-bar allow-list AND is a child of the local expander here. The bar
    // filters the resolved TOP level only, so being allow-listed is not enough to reach it — a
    // route has to be a first-level entry. That is what keeps the bar flat by construction
    // rather than by a second list somebody has to remember to keep in step.
    userStore.setUserAndPermissions(PLAIN_USER as never, ['inbox.read']);
    await wrapper.vm.$nextTick();

    expect(labelsOf(wrapper.findComponent(mobileStub).props('mobileItems'))).toEqual(['navigation.home']);
  });

  it('resolves a child route with its params, and leaves a literal label untranslated', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(PLAIN_USER as never, ['inbox.read']);
    await wrapper.vm.$nextTick();

    const projects = itemByLabel(wrapper, 'navigation.projects');

    expect(projects.route).toBe('/projects');
    expect(projects.children).toEqual([
      {
        icon: 'pi pi-heart',
        isLabelLiteral: true,
        key: 'project-7',
        label: 'Minha lista',
        route: '/projects/7',
        routeName: 'project',
      },
    ]);
  });

  it('routes a toggle through the store and persists it for the next visit', async () => {
    const { uiStore, wrapper } = mountNavbar();

    expect(wrapper.findComponent(desktopStub).props('expandedGroupKeys')).toEqual([]);

    await wrapper.findComponent(desktopStub).vm.$emit('toggle-group', 'navigation.workspace');

    expect(uiStore.expandedNavGroups).toEqual(['navigation.workspace']);
    expect(wrapper.findComponent(desktopStub).props('expandedGroupKeys')).toEqual(['navigation.workspace']);
    expect(wrapper.findComponent(drawerStub).props('expandedGroupKeys')).toEqual(['navigation.workspace']);
    expect(localStorage.getItem(NAV_EXPANDED_GROUPS_STORAGE_KEY)).toBe('navigation.workspace');
  });

  it('accepts a toggle from the drawer too', async () => {
    const { uiStore, wrapper } = mountNavbar();

    await wrapper.findComponent(drawerStub).vm.$emit('toggle-group', 'reports');

    expect(uiStore.expandedNavGroups).toEqual(['reports']);
  });

  /** One open group at a time, whichever variant asked: several open at once push the rest of
   *  the navigation past the fold, which is the thing an opened group must never do. */
  it('opens one group at a time across the rail and the drawer alike', async () => {
    const { uiStore, wrapper } = mountNavbar();

    await wrapper.findComponent(desktopStub).vm.$emit('toggle-group', 'navigation.workspace');
    await wrapper.findComponent(drawerStub).vm.$emit('toggle-group', 'reports');

    expect(uiStore.expandedNavGroups).toEqual(['reports']);
    expect(wrapper.findComponent(desktopStub).props('expandedGroupKeys')).toEqual(['reports']);
    expect(wrapper.findComponent(drawerStub).props('expandedGroupKeys')).toEqual(['reports']);
  });
});

describe('TheNavbar — drawer state', () => {
  it('opens the drawer when the bottom bar asks for it', async () => {
    const { uiStore, wrapper } = mountNavbar();

    await wrapper.findComponent(mobileStub).vm.$emit('toggle-drawer');

    expect(uiStore.isSidenavOpen).toBe(true);
    expect(wrapper.findComponent(drawerStub).props('visible')).toBe(true);
  });

  it('closes the drawer when it reports a close', async () => {
    const { uiStore, wrapper } = mountNavbar();

    uiStore.isSidenavOpen = true;
    await wrapper.vm.$nextTick();

    await wrapper.findComponent(drawerStub).vm.$emit('close');

    expect(uiStore.isSidenavOpen).toBe(false);
  });

  it('writes the drawer own visibility change back to the store', async () => {
    const { uiStore, wrapper } = mountNavbar();

    uiStore.isSidenavOpen = true;
    await wrapper.vm.$nextTick();

    await wrapper.findComponent(drawerStub).vm.$emit('update:visible', false);

    expect(uiStore.isSidenavOpen).toBe(false);
  });
});

describe('TheNavbar — logout', () => {
  it('ends the session, forgets the user and lands on login', async () => {
    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(SUPERADMIN as never, ['users.read']);

    await wrapper.findComponent(desktopStub).vm.$emit('logout');
    await flushPromises();

    expect(logoutService).toHaveBeenCalledOnce();
    expect(userStore.user).toBeNull();
    expect(userStore.permissions).toEqual([]);
    expect(router.currentRoute.value.path).toBe('/login');
  });

  /*
   * Clearing the user is not clearing the app. The feature stores hold the previous person's
   * lists and drafts behind "already loaded" guards, so without this the next sign-in
   * inside the same SPA session showed their data until a reload.
   */
  it('empties every feature store when the session ends', async () => {
    resetAllStores.mockClear();
    logoutService.mockResolvedValue(undefined);

    const { wrapper } = mountNavbar();

    await wrapper.findComponent(desktopStub).vm.$emit('logout');
    await flushPromises();

    expect(resetAllStores).toHaveBeenCalledOnce();
  });

  it('keeps the stores when the logout request fails', async () => {
    resetAllStores.mockClear();
    logoutService.mockRejectedValue(new Error('network'));

    const { wrapper } = mountNavbar();

    await wrapper.findComponent(desktopStub).vm.$emit('logout');
    await flushPromises();

    expect(resetAllStores).not.toHaveBeenCalled();
  });

  it('treats a 401 on logout as already signed out', async () => {
    resetAllStores.mockClear();
    logoutService.mockRejectedValue({ response: { status: 401 } });

    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(SUPERADMIN as never, ['users.read']);

    await wrapper.findComponent(desktopStub).vm.$emit('logout');
    await flushPromises();

    expect(resetAllStores).toHaveBeenCalledOnce();
    expect(userStore.user).toBeNull();
    expect(router.currentRoute.value.path).toBe('/login');
  });

  it('accepts the logout request from the drawer too', async () => {
    const { wrapper } = mountNavbar();

    await wrapper.findComponent(drawerStub).vm.$emit('logout');
    await flushPromises();

    expect(logoutService).toHaveBeenCalledOnce();
  });

  it('keeps the session when the logout request fails', async () => {
    logoutService.mockRejectedValue(new Error('network'));

    const { userStore, wrapper } = mountNavbar();

    userStore.setUserAndPermissions(SUPERADMIN as never, ['users.read']);

    await wrapper.findComponent(desktopStub).vm.$emit('logout');
    await flushPromises();

    expect(userStore.user).not.toBeNull();
    expect(router.currentRoute.value.path).toBe('/');
  });
});
