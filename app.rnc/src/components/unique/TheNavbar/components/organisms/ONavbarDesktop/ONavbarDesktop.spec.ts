import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { Router } from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import ONavbarDesktop from './ONavbarDesktop.vue';

const primevueStubs = {
  MNavbarBrand: { template: '<div class="logo-stub" />' },
  // Renders a real <button> because that is what PrimeVue renders, and because the group
  // toggles are Buttons too now — the class is `button-stub`, not `button-stub`, since this
  // stands in for five of them. Attributes fall through to the root, so a test can still ask
  // for `button[aria-expanded]`.
  Button: {
    emits: ['click'],
    template: '<button class="button-stub" @click="$emit(\'click\', $event)"><slot /></button>',
  },
  Card: { template: '<div class="card-stub"><slot name="content" /><slot name="footer" /></div>' },
  Divider: { template: '<hr class="divider-stub" />' },
};

const ITEMS_WITH_ROUTES = [
  { icon: 'pi pi-home', key: 'home', label: 'navigation.home', route: '/', routeName: 'home' },
];

/** Expander — children and no route of its own: the whole row toggles. Keyed by label. */
const EXPANDER_GROUP = {
  children: [{ icon: 'pi pi-inbox', key: 'inbox', label: 'navigation.inbox', route: '/inbox', routeName: 'inbox' }],
  icon: 'pi pi-compass',
  key: 'navigation.settings',
  label: 'navigation.settings',
};

/** Link with toggle — a route AND children: the text navigates, the chevron toggles. */
const LINKED_GROUP = {
  children: [
    { icon: 'pi pi-chart-line', key: 'trends', label: 'navigation.trends', route: '/trends', routeName: 'trends' },
  ],
  icon: 'pi pi-flag',
  key: 'reports',
  label: 'navigation.reports',
  route: '/reports',
  routeName: 'reports',
};

const PERSONALIZATION_WITH_ROUTE = {
  icon: 'pi pi-cog',
  key: 'personalization',
  label: 'navigation.personalization',
  route: '/personalization',
  routeName: 'personalization',
};

const SETTINGS_WITH_ROUTES = [
  { icon: 'pi pi-users', key: 'users', label: 'navigation.users', route: '/users', routeName: 'users' },
];

let router: Router;

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { name: 'home', path: '/', component: { template: '<div />' } },
      { name: 'users', path: '/users', component: { template: '<div />' } },
      { name: 'personalization', path: '/personalization', component: { template: '<div />' } },
      { name: 'inbox', path: '/inbox', component: { template: '<div />' } },
      { name: 'reports', path: '/reports', component: { template: '<div />' } },
      { name: 'trends', path: '/trends', component: { template: '<div />' } },
    ],
  });
}

function mountNavbar(props: Record<string, unknown> = {}) {
  return mountWithPlugins(ONavbarDesktop, {
    props: {
      expandedGroupKeys: [],
      itemsWithRoutes: ITEMS_WITH_ROUTES,
      personalizationWithRoute: PERSONALIZATION_WITH_ROUTE,
      settingsWithRoutes: SETTINGS_WITH_ROUTES,
      ...props,
    },
    global: { plugins: [router], stubs: primevueStubs },
  });
}

/**
 * jsdom has no layout, so the geometry the reveal reads is stated instead of measured: a group
 * whose bottom falls 50px past the fold of a 300px scroll area.
 */
function stubScrollGeometry(wrapper: ReturnType<typeof mountNavbar>) {
  const scrollArea = wrapper.find('.scrollbar-brand').element as HTMLElement;
  const groupElement = wrapper.find('[data-nav-group]').element as HTMLElement;
  const headerRow = groupElement.firstElementChild as HTMLElement;
  const childList = groupElement.querySelector('ul') as HTMLElement;

  scrollArea.getBoundingClientRect = () => ({ top: 0 }) as DOMRect;
  groupElement.getBoundingClientRect = () => ({ top: 250 }) as DOMRect;

  Object.defineProperty(scrollArea, 'clientHeight', { configurable: true, value: 300 });
  Object.defineProperty(scrollArea, 'scrollTop', { configurable: true, value: 0, writable: true });
  Object.defineProperty(headerRow, 'offsetHeight', { configurable: true, value: 40 });
  Object.defineProperty(childList, 'offsetHeight', { configurable: true, value: 60 });

  scrollArea.scrollTo = vi.fn();

  return scrollArea;
}

beforeEach(async () => {
  router = buildRouter();
  router.push('/');
  await router.isReady();
});

describe('ONavbarDesktop', () => {
  it('renders a link per navigation item with its translated label', () => {
    const wrapper = mountNavbar();

    expect(wrapper.text()).toContain('Home');
  });

  it('renders a link per settings item', () => {
    const wrapper = mountNavbar();

    expect(wrapper.text()).toContain('Users');
  });

  it('always renders the personalization link', () => {
    const wrapper = mountNavbar();

    expect(wrapper.text()).toContain('Personalization');
    expect(wrapper.find('a[href="/personalization"]').exists()).toBe(true);
  });

  it('points each link at the route it was given', () => {
    const wrapper = mountNavbar();

    expect(wrapper.find('a[href="/"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/users"]').exists()).toBe(true);
  });

  it('renders the item icon beside its label', () => {
    const wrapper = mountNavbar();

    expect(wrapper.find('.pi-home').exists()).toBe(true);
    expect(wrapper.find('.pi-cog').exists()).toBe(true);
  });

  it('highlights the link matching the current route exactly', async () => {
    await router.push('/users');

    const wrapper = mountNavbar();
    await wrapper.vm.$nextTick();

    expect(wrapper.find('a[href="/users"]').classes()).toContain('bg-panel');
    expect(wrapper.find('a[href="/"]').classes()).not.toContain('bg-panel');
  });

  /*
   * The dividers take their colour from the preset (`divider.root.borderColor` is `--color-line`).
   * A `before:border-line` here was a call site undoing an old brand-coloured default.
   */
  it('leaves the divider colour to the preset', () => {
    const wrapper = mountNavbar();
    const dividers = wrapper.findAll('.divider-stub');

    expect(dividers.length).toBeGreaterThan(0);

    for (const divider of dividers) {
      expect(divider.classes()).not.toContain('before:border-line');
    }
  });

  it('renders the mark at the top of the rail', () => {
    const wrapper = mountNavbar();

    expect(wrapper.find('.logo-stub').exists()).toBe(true);
  });

  it('emits logout when the logout button is pressed', async () => {
    const wrapper = mountNavbar();

    await wrapper.find('.button-stub').trigger('click');

    expect(wrapper.emitted('logout')).toHaveLength(1);
    expect(wrapper.emitted('logout')?.[0]).toEqual([]);
  });

  it('renders no navigation links when every item was filtered out', () => {
    const wrapper = mountNavbar({ itemsWithRoutes: [], settingsWithRoutes: [] });

    // Personalization is unconditional, so exactly one link survives.
    expect(wrapper.findAll('a')).toHaveLength(1);
    expect(wrapper.find('a').attributes('href')).toBe('/personalization');
  });

  it('stays hidden until the desktop breakpoint, where the rail is the navigation', () => {
    const wrapper = mountNavbar();

    expect(wrapper.classes()).toContain('hidden');
    expect(wrapper.classes()).toContain('lg:block');
  });
});

describe('ONavbarDesktop — groups', () => {
  /** The header row wraps the parent link and the chevron — it is where the group-active hint
   *  lands, and the only way to tell that hint apart from the link's own exact-active class. */
  function headerRowOf(wrapper: ReturnType<typeof mountNavbar>, href: string) {
    return wrapper.find(`a[href="${href}"]`).element.parentElement!.classList;
  }

  it('renders an expander group as a toggle, not a link', async () => {
    const wrapper = mountNavbar({ itemsWithRoutes: [EXPANDER_GROUP] });

    const toggle = wrapper.find('button[aria-expanded]');

    expect(toggle.text()).toContain('Settings');
    expect(wrapper.find('a[href="/inbox"]').exists()).toBe(true);

    await toggle.trigger('click');

    expect(wrapper.emitted('toggle-group')).toEqual([['navigation.settings']]);
  });

  it('keeps the text a link and hands the chevron the toggle when the group has a route', async () => {
    const wrapper = mountNavbar({ itemsWithRoutes: [LINKED_GROUP] });

    expect(wrapper.find('a[href="/reports"]').exists()).toBe(true);

    await wrapper.find('button[aria-expanded]').trigger('click');

    expect(wrapper.emitted('toggle-group')).toEqual([['reports']]);
  });

  it('collapses children to a zero track and expands them from the prop', () => {
    const collapsed = mountNavbar({ itemsWithRoutes: [LINKED_GROUP] });
    const expanded = mountNavbar({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });

    expect(collapsed.find('.grid-rows-\\[0fr\\]').exists()).toBe(true);
    expect(collapsed.find('button[aria-expanded]').attributes('aria-expanded')).toBe('false');
    expect(expanded.find('.grid-rows-\\[1fr\\]').exists()).toBe(true);
    expect(expanded.find('button[aria-expanded]').attributes('aria-expanded')).toBe('true');
  });

  it('marks the parent active while a child route is on screen', async () => {
    await router.push('/trends');

    const wrapper = mountNavbar({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });
    await wrapper.vm.$nextTick();

    // The quiet hint sits on the header row and stops there — the parent link itself is not
    // exact-active, and only the child carries the peach icon that says "you are here".
    expect(headerRowOf(wrapper, '/reports').contains('bg-panel')).toBe(true);
    expect(wrapper.find('a[href="/reports"]').classes()).not.toContain('bg-panel');
    expect(wrapper.find('a[href="/trends"]').classes()).toContain('bg-panel');
    expect(wrapper.find('a[href="/trends"] .pi-chart-line').classes()).toContain('bg-primary-100');
  });

  it('leaves the parent quiet when none of its children is active', () => {
    const wrapper = mountNavbar({ itemsWithRoutes: [LINKED_GROUP] });

    expect(headerRowOf(wrapper, '/reports').contains('bg-panel')).toBe(false);
  });

  it('takes a collapsed group out of tab order instead of leaving clipped links reachable', () => {
    const collapsed = mountNavbar({ itemsWithRoutes: [LINKED_GROUP] });
    const expanded = mountNavbar({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });

    // `inert="false"` reads as inert to HTML, so absent is the only "off".
    expect(collapsed.find('[inert] a[href="/trends"]').exists()).toBe(true);
    expect(expanded.find('[inert]').exists()).toBe(false);
  });

  it('names each group in the DOM so the scroll area can find the one that opened', () => {
    const wrapper = mountNavbar({ itemsWithRoutes: [LINKED_GROUP] });

    expect(wrapper.find('[data-nav-group="reports"]').exists()).toBe(true);
  });

  it('scrolls an open group into view once its children finish growing', async () => {
    const wrapper = mountNavbar({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });
    const scrollArea = stubScrollGeometry(wrapper);

    await wrapper.find('.grid-rows-\\[1fr\\]').trigger('transitionend');

    // 250 + (40 + 60) - 300 = 50px past the fold of the scroll area.
    expect(scrollArea.scrollTo).toHaveBeenCalledWith({ top: 50, behavior: 'smooth' });
  });

  it('leaves the scroll position alone when a group is collapsing', async () => {
    const wrapper = mountNavbar({ itemsWithRoutes: [LINKED_GROUP] });
    const scrollArea = stubScrollGeometry(wrapper);

    await wrapper.find('.grid-rows-\\[0fr\\]').trigger('transitionend');

    expect(scrollArea.scrollTo).not.toHaveBeenCalled();
  });
});
