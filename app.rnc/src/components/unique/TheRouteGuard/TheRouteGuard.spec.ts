import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type * as UserModule from '@UserModule';
import type { Pinia } from 'pinia';
import type { Router } from 'vue-router';
import { getLoginPath } from '@Helpers';
import i18n from '@Libraries/i18n';
import ThemePreset from '@Libraries/primevue/theme';

const { captureException, getMeService } = vi.hoisted(() => ({
  captureException: vi.fn(),
  getMeService: vi.fn(),
}));

vi.mock('@sentry/vue', () => ({ captureException }));

vi.mock('@UserModule', async (importActual) => {
  const actual = await importActual<typeof UserModule>();

  return { ...actual, GetMeService: getMeService };
});

const { useUserStore } = await import('@UserModule');
const { default: TheRouteGuard } = await import('./TheRouteGuard.vue');

const SESSION_HINT_KEY = 'app.rnc:session-hint';

const mockUser = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'admin',
  permissions: ['users.read'],
};

const blank = { template: '<div />' };

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { name: 'home', path: '/', component: blank },
      { name: 'login', path: '/entrar', component: blank, meta: { isPublic: true } },
      { name: 'login-en', path: '/login', component: blank, meta: { isPublic: true } },
      { name: 'protected', path: '/protected', component: blank },
    ],
  });
}

function mountGuard(router: Router, pinia: Pinia) {
  return mount(TheRouteGuard, {
    slots: { default: '<div class="protected-content">secret</div>' },
    global: {
      plugins: [i18n, pinia, [PrimeVue, { theme: { preset: ThemePreset } }], ToastService, ConfirmationService, router],
      directives: { tooltip: () => {} },
    },
  });
}

beforeEach(() => {
  localStorage.clear();
  getMeService.mockReset();
  captureException.mockReset();
  getMeService.mockResolvedValue({ data: null });
});

describe('TheRouteGuard — boot session probe', () => {
  it('skips the /auth/me probe on a public route with no session hint', async () => {
    const router = buildRouter();
    router.push('/entrar');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);

    const wrapper = mountGuard(router, pinia);
    await flushPromises();

    expect(getMeService).not.toHaveBeenCalled();
    expect(wrapper.find('.protected-content').exists()).toBe(true);
  });

  it('skips the probe and redirects to login on a protected route with no session hint', async () => {
    const router = buildRouter();
    router.push('/protected');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);

    mountGuard(router, pinia);
    await flushPromises();

    expect(getMeService).not.toHaveBeenCalled();
    expect(router.currentRoute.value.path).toBe(getLoginPath());
  });

  it('still probes /auth/me on a protected route when a session hint exists', async () => {
    const router = buildRouter();
    router.push('/protected');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);
    localStorage.setItem(SESSION_HINT_KEY, '1');

    mountGuard(router, pinia);
    await flushPromises();

    expect(getMeService).toHaveBeenCalledOnce();
  });

  it('renders immediately for an already authenticated user without probing', async () => {
    const router = buildRouter();
    router.push('/protected');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);
    useUserStore().setUserAndPermissions(mockUser as never, ['users.read']);

    const wrapper = mountGuard(router, pinia);
    await flushPromises();

    expect(getMeService).not.toHaveBeenCalled();
    expect(wrapper.find('.protected-content').exists()).toBe(true);
  });
});

describe('TheRouteGuard — where a redirect goes', () => {
  it('sends a signed-out visitor to the login path of their language, carrying the destination', async () => {
    const router = buildRouter();
    router.push('/protected?tab=2');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);

    mountGuard(router, pinia);
    await flushPromises();

    // A hard-coded `/entrar` dropped the destination: signing in landed on Home, and an English
    // reader was sent to the Portuguese URL.
    expect(router.currentRoute.value.path).toBe(getLoginPath());
    expect(router.currentRoute.value.query.redirect).toBe('/protected?tab=2');
  });

  it('sends a signed-in person on the login screen to the destination it carried', async () => {
    const router = buildRouter();
    router.push('/entrar?redirect=/protected');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);
    useUserStore().setUserAndPermissions(mockUser as never, ['users.read']);

    mountGuard(router, pinia);
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/protected');
  });

  it('refuses a destination that names another host, and goes Home', async () => {
    const router = buildRouter();
    router.push('/entrar?redirect=//evil.test');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);
    useUserStore().setUserAndPermissions(mockUser as never, ['users.read']);

    mountGuard(router, pinia);
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('home');
  });
});

describe('TheRouteGuard — failures are handled, never swallowed', () => {
  it('does not report a redirect the router aborted — that is the router doing its job', async () => {
    const router = buildRouter();
    router.push('/protected');
    await router.isReady();
    router.beforeEach((to) => !to.meta.isPublic);

    const pinia = createPinia();
    setActivePinia(pinia);

    mountGuard(router, pinia);
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('protected');
    expect(captureException).not.toHaveBeenCalled();
  });

  it('reports a redirect that failed for any other reason', async () => {
    const router = buildRouter();
    router.push('/protected');
    await router.isReady();

    const BROKEN_GUARD = new Error('a guard threw');
    router.beforeEach((to) => {
      if (to.meta.isPublic) throw BROKEN_GUARD;
    });

    const pinia = createPinia();
    setActivePinia(pinia);

    mountGuard(router, pinia);
    await flushPromises();

    expect(captureException).toHaveBeenCalledWith(BROKEN_GUARD);
  });

  it('leaves an HTTP failure of the session probe to the API provider, and still redirects', async () => {
    getMeService.mockRejectedValue(Object.assign(new Error('Unauthorized'), { isAxiosError: true }));

    const router = buildRouter();
    router.push('/protected');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);
    localStorage.setItem(SESSION_HINT_KEY, '1');

    mountGuard(router, pinia);
    await flushPromises();

    expect(captureException).not.toHaveBeenCalled();
    expect(router.currentRoute.value.path).toBe(getLoginPath());
  });

  it('reports a probe failure the provider never saw', async () => {
    const BROKEN_STORE = new TypeError('cannot store the user');
    getMeService.mockRejectedValue(BROKEN_STORE);

    const router = buildRouter();
    router.push('/entrar');
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);
    localStorage.setItem(SESSION_HINT_KEY, '1');

    mountGuard(router, pinia);
    await flushPromises();

    expect(captureException).toHaveBeenCalledWith(BROKEN_STORE);
  });
});
