import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { Router } from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import { FULLSCREEN_STORAGE_KEY } from '@Constants';
import useUiStore from '@Stores';
import ONavbarMobile from './ONavbarMobile.vue';

const primevueStubs = {
  Button: {
    props: ['icon'],
    emits: ['click'],
    template: '<button class="button-stub" :data-icon="icon" @click="$emit(\'click\', $event)" />',
  },
};

const MOBILE_ITEMS = [
  { icon: 'pi pi-home', key: 'home', label: 'navigation.home', route: '/', routeName: 'home' },
  { icon: 'pi pi-users', key: 'users', label: 'navigation.users', route: '/users', routeName: 'users' },
];

const PERSONALIZATION_WITH_ROUTE = {
  icon: 'pi pi-cog',
  key: 'personalization',
  label: 'navigation.personalization',
  route: '/personalization',
  routeName: 'personalization',
};

const DRAWER_BUTTON = '[data-icon="pi pi-bars"]';

let router: Router;

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { name: 'home', path: '/', component: { template: '<div />' } },
      { name: 'users', path: '/users', component: { template: '<div />' } },
      { name: 'personalization', path: '/personalization', component: { template: '<div />' } },
    ],
  });
}

function mountMobile(props: Record<string, unknown> = {}) {
  return mountWithPlugins(ONavbarMobile, {
    props: {
      mobileItems: MOBILE_ITEMS,
      personalizationWithRoute: PERSONALIZATION_WITH_ROUTE,
      ...props,
    },
    global: { plugins: [router], stubs: primevueStubs },
  });
}

beforeEach(async () => {
  localStorage.clear();

  router = buildRouter();
  router.push('/');
  await router.isReady();
});

afterEach(() => localStorage.clear());

describe('ONavbarMobile', () => {
  it('renders a link per mobile item plus the personalization shortcut', () => {
    const wrapper = mountMobile();

    expect(wrapper.findAll('a')).toHaveLength(MOBILE_ITEMS.length + 1);
  });

  it('points each link at the route it was given', () => {
    const wrapper = mountMobile();

    expect(wrapper.find('a[href="/users"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/personalization"]').exists()).toBe(true);
  });

  it('shows the item as an icon only, so the bar fits a phone', () => {
    const wrapper = mountMobile();

    expect(wrapper.find('.pi-users').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Users');
  });

  it('labels the personalization icon for screen readers', () => {
    const wrapper = mountMobile();

    expect(wrapper.find('a[href="/personalization"]').attributes('aria-label')).toBe('Personalization');
  });

  it('emits toggle-drawer when the hamburger is pressed', async () => {
    const wrapper = mountMobile();

    await wrapper.find(DRAWER_BUTTON).trigger('click');

    expect(wrapper.emitted('toggle-drawer')).toHaveLength(1);
    expect(wrapper.emitted('toggle-drawer')?.[0]).toEqual([]);
  });

  it('keeps the hamburger even when every item was filtered out', () => {
    const wrapper = mountMobile({ mobileItems: [] });

    expect(wrapper.find(DRAWER_BUTTON).exists()).toBe(true);
    expect(wrapper.findAll('a')).toHaveLength(1);
  });

  it('marks the link for the current route as the active one', async () => {
    await router.push('/users');

    const wrapper = mountMobile();
    await wrapper.vm.$nextTick();

    expect(wrapper.find('a[href="/users"]').classes()).toContain('bg-primary-100');
    expect(wrapper.find('a[href="/"]').classes()).not.toContain('bg-primary-100');
  });

  /*
   * `lg`, not `md`. The desktop rail only appears at `lg`, so hiding the bar at `md` left every
   * width from 768 to 1023 — the whole iPad-portrait range — with no persistent navigation at all.
   * The two breakpoints have to be the same one.
   */
  it('paints the backdrop and the bar only below the rail breakpoint', () => {
    const wrapper = mountMobile();

    expect(wrapper.find('.mobile-navbar-backdrop').classes()).toContain('lg:hidden');
    expect(wrapper.find('.mobile-navbar-root').classes()).toContain('lg:hidden');
  });

  it('keeps the backdrop out of the way of pointer events', () => {
    const wrapper = mountMobile();

    expect(wrapper.find('.mobile-navbar-backdrop').classes()).toContain('pointer-events-none');
  });

  it('lands on the page column instead of the window edges', () => {
    localStorage.setItem(FULLSCREEN_STORAGE_KEY, '0');

    const rootClasses = mountMobile().find('.mobile-navbar-root').classes();

    expect(rootClasses).toContain('mx-auto');
    /*
     * The whole ladder, and the same step points as `TheLayout` — not just `sm`. The bar is on
     * screen until `lg` now, so a flat `sm:max-w-[640px]` left it 128px narrower than the column
     * above it on a tablet, and `sm:px-8` put it on 32px of padding while the page still had 24.
     */
    expect(rootClasses).toContain('sm:max-w-[640px]');
    expect(rootClasses).toContain('md:max-w-[768px]');
    expect(rootClasses).toContain('lg:max-w-[1024px]');
    expect(rootClasses).toContain('px-6');
    expect(rootClasses).toContain('md:px-8');
  });

  it('follows the narrower rhythm the layout uses in fullscreen', async () => {
    // Turned on rather than assumed: an absent stored value means fullscreen OFF in this
    // bootstrap, so a test that leans on the default would be asserting the preference.
    const wrapper = mountMobile();

    useUiStore().setFullscreen(true);
    await wrapper.vm.$nextTick();

    const rootClasses = wrapper.find('.mobile-navbar-root').classes();

    expect(rootClasses).toContain('max-w-none');
    expect(rootClasses).toContain('px-3');
    expect(rootClasses).toContain('sm:px-4');
  });

  it('rounds every corner, so the bar reads as a floating surface', () => {
    const barClasses = mountMobile().find('.mobile-navbar-root > div').classes();

    expect(barClasses).toContain('rounded-xl');
    expect(barClasses).not.toContain('rounded-t-xl');
  });

  /*
   * `max(float, safe-area)`, never their sum — and the variable is what guarantees the bar, the
   * backdrop behind it and TheLayout's scroll reserve all agree on one number. Adding them is what
   * left this bar visibly too high in Firefox Android, which reports an inset where Chrome
   * reports none: the person got the 16px float AND the inset, with empty canvas underneath.
   */
  it('takes the larger of the float gap and the home indicator, never both', () => {
    const rootClasses = mountMobile().find('.mobile-navbar-root').classes();

    expect(rootClasses).toContain('pb-[var(--mobile-nav-gap)]');
    expect(rootClasses.some((name) => name.includes('1rem+env'))).toBe(false);
  });
});
