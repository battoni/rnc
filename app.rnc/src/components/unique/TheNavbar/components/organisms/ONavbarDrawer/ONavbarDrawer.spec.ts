import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { Router } from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import ONavbarDrawer from './ONavbarDrawer.vue';

// The Drawer owns the close animation, so it hands the container slot a callback instead of
// letting the content flip `visible` itself. The spy stands in for that callback.
const closeCallback = vi.fn();

const drawerStub = {
  name: 'Drawer',
  props: ['position', 'visible'],
  emits: ['update:visible'],
  setup() {
    return { closeCallback };
  },
  template:
    '<div v-if="visible" class="drawer-stub" :data-position="position"><slot :closeCallback="closeCallback" name="container" /></div>',
};

const primevueStubs = {
  ALogo: { template: '<div class="logo-stub" />' },
  Button: {
    props: ['icon'],
    emits: ['click'],
    template: '<button class="button-stub" :data-icon="icon" @click="$emit(\'click\', $event)"><slot /></button>',
  },
  Divider: { template: '<hr class="divider-stub" />' },
  Drawer: drawerStub,
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

const CLOSE_BUTTON = '[data-icon="pi pi-times"]';
const LOGOUT_BUTTON = 'button.button-stub:not([data-icon])';

let router: Router;

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { name: 'home', path: '/', component: { template: '<div />' } },
      { name: 'users', path: '/users', component: { template: '<div />' } },
      { name: 'personalization', path: '/personalization', component: { template: '<div />' } },
      { name: 'reports', path: '/reports', component: { template: '<div />' } },
      { name: 'trends', path: '/trends', component: { template: '<div />' } },
      { name: 'inbox', path: '/inbox', component: { template: '<div />' } },
    ],
  });
}

function mountDrawer(props: Record<string, unknown> = {}) {
  return mountWithPlugins(ONavbarDrawer, {
    props: {
      drawerClass: 'w-54',
      drawerPosition: 'left',
      expandedGroupKeys: [],
      itemsWithRoutes: ITEMS_WITH_ROUTES,
      personalizationWithRoute: PERSONALIZATION_WITH_ROUTE,
      settingsWithRoutes: SETTINGS_WITH_ROUTES,
      visible: true,
      ...props,
    },
    global: { plugins: [router], stubs: primevueStubs },
  });
}

/**
 * jsdom has no layout, so the geometry the reveal reads is stated instead of measured: a group
 * whose bottom falls 50px past the fold of a 300px scroll area.
 */
function stubScrollGeometry(wrapper: ReturnType<typeof mountDrawer>) {
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
  closeCallback.mockReset();
  router = buildRouter();
  router.push('/');
  await router.isReady();
});

describe('ONavbarDrawer', () => {
  /** The header row wraps the parent link and the chevron — it is where the group-active hint
   *  lands, and the only way to tell that hint apart from the link's own exact-active class. */
  function headerRowOf(wrapper: ReturnType<typeof mountDrawer>, href: string) {
    return wrapper.find(`a[href="${href}"]`).element.parentElement!.classList;
  }

  it('renders nothing while it is not visible', () => {
    const wrapper = mountDrawer({ visible: false });

    expect(wrapper.find('.drawer-stub').exists()).toBe(false);
  });

  it('renders every navigation and settings item once open', () => {
    const wrapper = mountDrawer();

    expect(wrapper.text()).toContain('Home');
    expect(wrapper.text()).toContain('Users');
    expect(wrapper.text()).toContain('Personalization');
  });

  it('points each link at the route it was given', () => {
    const wrapper = mountDrawer();

    expect(wrapper.find('a[href="/users"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/personalization"]').exists()).toBe(true);
  });

  it('takes its position from the prop so mobile can go full bleed', () => {
    const wrapper = mountDrawer({ drawerClass: 'nav-drawer-mobile', drawerPosition: 'full' });

    expect(wrapper.find('.drawer-stub').attributes('data-position')).toBe('full');
  });

  it('emits close and runs the drawer callback when the close button is pressed', async () => {
    const wrapper = mountDrawer();

    await wrapper.find(CLOSE_BUTTON).trigger('click');

    expect(closeCallback).toHaveBeenCalledOnce();
    expect(wrapper.emitted('close')).toHaveLength(1);
  });

  it('names the icon-only close button for a screen reader', () => {
    const wrapper = mountDrawer();

    expect(wrapper.find(CLOSE_BUTTON).attributes('aria-label')).toBe('Close');
  });

  /*
   * The dividers take their colour from the preset (`divider.root.borderColor` is `--color-line`).
   * A `before:border-line` here was a call site undoing an old brand-coloured default.
   */
  it('leaves the divider colour to the preset', () => {
    const wrapper = mountDrawer();
    const dividers = wrapper.findAll('.divider-stub');

    expect(dividers.length).toBeGreaterThan(0);

    for (const divider of dividers) {
      expect(divider.classes()).not.toContain('before:border-line');
    }
  });

  it('closes itself when a navigation link is followed', async () => {
    const wrapper = mountDrawer();

    await wrapper.find('a[href="/users"]').trigger('click');

    expect(wrapper.emitted('close')).toHaveLength(1);
  });

  it('emits logout after closing, so the drawer never outlives the session', async () => {
    const wrapper = mountDrawer();

    await wrapper.find(LOGOUT_BUTTON).trigger('click');

    expect(closeCallback).toHaveBeenCalledOnce();
    expect(wrapper.emitted('close')).toHaveLength(1);
    expect(wrapper.emitted('logout')).toHaveLength(1);
  });

  it('forwards the drawer own visibility change to the parent model', () => {
    const wrapper = mountDrawer();

    wrapper.findComponent(drawerStub).vm.$emit('update:visible', false);

    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false]);
  });

  it('stays out of the way at desktop width, where the rail is the navigation', () => {
    const wrapper = mountDrawer();

    expect(wrapper.find('.drawer-stub').classes()).toContain('lg:hidden');
    expect(wrapper.find('.drawer-stub').classes()).toContain('w-54');
  });

  it('keeps the drawer open when a group is toggled — only navigation closes it', async () => {
    const wrapper = mountDrawer({ itemsWithRoutes: [LINKED_GROUP] });

    await wrapper.find('button[aria-expanded]').trigger('click');

    expect(wrapper.emitted('toggle-group')).toEqual([['reports']]);
    expect(wrapper.emitted('close')).toBeUndefined();
    expect(closeCallback).not.toHaveBeenCalled();
  });

  it('closes itself when a child link is followed', async () => {
    const wrapper = mountDrawer({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });

    await wrapper.find('a[href="/trends"]').trigger('click');

    expect(wrapper.emitted('close')).toHaveLength(1);
  });

  it('turns the whole row into the toggle when the group has no route of its own', async () => {
    const wrapper = mountDrawer({ itemsWithRoutes: [EXPANDER_GROUP] });

    const toggle = wrapper.find('button[aria-expanded]');

    expect(toggle.text()).toContain('Settings');
    expect(wrapper.find('a[href="/inbox"]').exists()).toBe(true);

    await toggle.trigger('click');

    expect(wrapper.emitted('toggle-group')).toEqual([['navigation.settings']]);
    expect(wrapper.emitted('close')).toBeUndefined();
  });

  it('collapses children to a zero track and expands them from the prop', () => {
    const collapsed = mountDrawer({ itemsWithRoutes: [LINKED_GROUP] });
    const expanded = mountDrawer({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });

    expect(collapsed.find('.grid-rows-\\[0fr\\]').exists()).toBe(true);
    expect(collapsed.find('button[aria-expanded]').attributes('aria-expanded')).toBe('false');
    expect(expanded.find('.grid-rows-\\[1fr\\]').exists()).toBe(true);
    expect(expanded.find('button[aria-expanded]').attributes('aria-expanded')).toBe('true');
  });

  it('marks the parent active while a child route is on screen', async () => {
    await router.push('/trends');

    const wrapper = mountDrawer({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });
    await wrapper.vm.$nextTick();

    // The quiet hint sits on the header row and stops there — only the child carries the peach.
    expect(headerRowOf(wrapper, '/reports').contains('bg-panel')).toBe(true);
    expect(wrapper.find('a[href="/reports"]').classes()).not.toContain('bg-panel');
    expect(wrapper.find('a[href="/trends"] .pi-chart-line').classes()).toContain('bg-primary-100');
  });

  it('leaves the parent quiet when none of its children is active', () => {
    const wrapper = mountDrawer({ itemsWithRoutes: [LINKED_GROUP] });

    expect(headerRowOf(wrapper, '/reports').contains('bg-panel')).toBe(false);
  });

  it('takes a collapsed group out of tab order instead of leaving clipped links reachable', () => {
    const collapsed = mountDrawer({ itemsWithRoutes: [LINKED_GROUP] });
    const expanded = mountDrawer({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });

    // `inert="false"` reads as inert to HTML, so absent is the only "off".
    expect(collapsed.find('[inert] a[href="/trends"]').exists()).toBe(true);
    expect(expanded.find('[inert]').exists()).toBe(false);
  });

  it('names each group in the DOM so the scroll area can find the one that opened', () => {
    const wrapper = mountDrawer({ itemsWithRoutes: [LINKED_GROUP] });

    expect(wrapper.find('[data-nav-group="reports"]').exists()).toBe(true);
  });

  it('scrolls an open group into view once its children finish growing', async () => {
    const wrapper = mountDrawer({ expandedGroupKeys: ['reports'], itemsWithRoutes: [LINKED_GROUP] });
    const scrollArea = stubScrollGeometry(wrapper);

    await wrapper.find('.grid-rows-\\[1fr\\]').trigger('transitionend');

    // 250 + (40 + 60) - 300 = 50px past the fold of the scroll area.
    expect(scrollArea.scrollTo).toHaveBeenCalledWith({ top: 50, behavior: 'smooth' });
  });

  it('leaves the scroll position alone when a group is collapsing', async () => {
    const wrapper = mountDrawer({ itemsWithRoutes: [LINKED_GROUP] });
    const scrollArea = stubScrollGeometry(wrapper);

    await wrapper.find('.grid-rows-\\[0fr\\]').trigger('transitionend');

    expect(scrollArea.scrollTo).not.toHaveBeenCalled();
  });
});
