import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import useUiStore from '@Stores';
import TheLayout from './TheLayout.vue';

const navbarStub = { name: 'TheNavbar', template: '<nav class="navbar-stub" />' };
const menuButtonStub = { name: 'AMenuButton', template: '<button class="menu-button-stub" />' };

const globalStubs = {
  TheNavbar: navbarStub,
  AMenuButton: menuButtonStub,
};

function mountLayout(options: { props?: Record<string, unknown>; slots?: Record<string, string> } = {}) {
  const wrapper = mountWithPlugins(TheLayout, {
    props: options.props,
    slots: options.slots,
    global: { stubs: globalStubs },
  });

  // mountWithPlugins installs its own Pinia, so the store must be read after mounting or
  // the assertions inspect a different instance from the one the component holds.
  return { store: useUiStore(), wrapper };
}

describe('TheLayout', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('renders the page content in the default slot', () => {
    const { wrapper } = mountLayout({ slots: { default: '<p class="page-body">Body</p>' } });

    expect(wrapper.find('.page-body').text()).toBe('Body');
  });

  it('always mounts the navigation rail', () => {
    const { wrapper } = mountLayout();

    expect(wrapper.find('.navbar-stub').exists()).toBe(true);
  });

  it('renders the page header when the page provides one', () => {
    const { wrapper } = mountLayout({ slots: { pageHeader: '<h1 class="page-header">Users</h1>' } });

    expect(wrapper.find('.page-header').text()).toBe('Users');
  });

  it('leaves the header row to the page and adds no menu button of its own', () => {
    const { wrapper } = mountLayout({ slots: { pageHeader: '<h1 class="page-header">Users</h1>' } });

    // ThePageHeader carries its own AMenuButton; a second one here would be a duplicate.
    expect(wrapper.find('.menu-button-stub').exists()).toBe(false);
  });

  it('renders its own menu button when the page has no header', () => {
    const { wrapper } = mountLayout({ slots: { default: '<p class="page-body">Body</p>' } });

    // Collapsing the rail is persisted, so a headerless page with no button would strand
    // the app with no route back to the navigation.
    expect(wrapper.find('.menu-button-stub').exists()).toBe(true);
  });

  it('passes the classes prop through to main', () => {
    const { wrapper } = mountLayout({ props: { classes: 'items-center' } });

    expect(wrapper.find('main').classes()).toContain('items-center');
  });

  it('renders main without extra classes when the prop is absent', () => {
    const { wrapper } = mountLayout();

    expect(wrapper.find('main').exists()).toBe(true);
  });

  it('lets the content run edge to edge while fullscreen is on', async () => {
    const { store, wrapper } = mountLayout({ slots: { default: '<p class="page-body">Body</p>' } });

    // Turned on rather than assumed: an absent stored value means OFF in this bootstrap, and a
    // test that leans on a default is testing the preference instead of the layout.
    store.setFullscreen(true);
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.main-scroll').classes()).toContain('px-3');
    expect(wrapper.find('main').element.parentElement?.className).toContain('max-w-none');
  });

  it('narrows the column when fullscreen is turned off', async () => {
    const { store, wrapper } = mountLayout({ slots: { default: '<p class="page-body">Body</p>' } });

    store.setFullscreen(false);
    await wrapper.vm.$nextTick();

    // 24px on a phone, the original 32px from `md`. A flat `px-8` spent a sixth of a 390px
    // screen on margin and pushed the header row onto two lines.
    expect(wrapper.find('.main-scroll').classes()).toContain('px-6');
    expect(wrapper.find('.main-scroll').classes()).toContain('md:px-8');
    expect(wrapper.find('main').element.parentElement?.className).toContain('max-w-[1200px]');
  });

  it('widens the column into the freed space when the rail is collapsed', async () => {
    const { store, wrapper } = mountLayout({ slots: { default: '<p class="page-body">Body</p>' } });

    store.setFullscreen(false);
    store.toggleNavCollapsed();
    await wrapper.vm.$nextTick();

    /*
     * The column takes the whole outer wrapper, which is what the narrow layout allows at that
     * breakpoint. It used to take `max-w-[1448px]` — 1200 plus the rail's 248px footprint — which
     * held the right edge still but stranded 88px inside the outer wrapper at 2xl, so the page sat
     * 192px from the left against 280 from the right. One ladder, so the two caps cannot disagree.
     */
    const CLASSES = wrapper.find('main').element.parentElement?.className ?? '';

    // The ladder, not a bare cap: an unprefixed `max-w-[…]` here is the old two-numbers bug.
    expect(CLASSES).toContain('2xl:max-w-[1448px]');
    expect(CLASSES).toContain('xl:max-w-[1280px]');
    expect(CLASSES).not.toMatch(/(?:^|\s)max-w-\[/);
  });

  it('keeps the column edge to edge when the rail collapses in fullscreen', async () => {
    const { store, wrapper } = mountLayout({ slots: { default: '<p class="page-body">Body</p>' } });

    store.setFullscreen(true);
    store.toggleNavCollapsed();
    await wrapper.vm.$nextTick();

    expect(wrapper.find('main').element.parentElement?.className).toContain('max-w-none');
  });
});
