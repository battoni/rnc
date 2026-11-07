import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { Router } from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import OTabPanel from './OTabPanel.vue';
import OTabView from './OTabView.vue';

const tabsStub = {
  name: 'Tabs',
  props: ['value'],
  emits: ['update:value'],
  template: '<div class="tabs-stub"><slot /></div>',
};

const tabListStub = {
  name: 'TabList',
  template: '<div class="tablist-stub"><slot /></div>',
};

const tabStub = {
  name: 'Tab',
  props: ['value'],
  template: '<div class="tab-stub"><slot /></div>',
};

const primevueStubs = {
  ATip: { name: 'ATip', props: ['tipKey'], template: '<span class="tip-stub">{{ tipKey }}</span>' },
  Tabs: tabsStub,
  TabList: tabListStub,
  Tab: tabStub,
};

const viewSlot = `
  <OTabPanel title="Overview" value="overview">
    <template #actions><button class="edit-action">Edit</button></template>

    <p class="overview-content">Overview body</p>
  </OTabPanel>

  <OTabPanel icon="pi pi-bolt" title="Activity" value="activity">
    <p class="activity-content">Activity body</p>
  </OTabPanel>
`;

const routesSlot = `
  <OTabPanel title="Tab A" value="a" :to="{ name: 'tab-a' }" />

  <OTabPanel title="Tab B" value="b" :to="{ name: 'tab-b' }" />
`;

let router: Router;

function buildRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { name: 'tab-a', path: '/a', component: { template: '<div class="route-a">Route A</div>' } },
      { name: 'tab-b', path: '/b', component: { template: '<div class="route-b">Route B</div>' } },
    ],
  });
}

function mount(options: { props?: Record<string, unknown>; slot?: string } = {}) {
  return mountWithPlugins(OTabView, {
    props: options.props,
    slots: { default: options.slot ?? viewSlot },
    global: {
      components: { OTabPanel },
      plugins: [router],
      stubs: primevueStubs,
    },
  });
}

beforeEach(async () => {
  router = buildRouter();
  // Land on a matched route before mounting so the router install does not warn about
  // resolving the empty start location. Routes-mode tests re-navigate as needed.
  router.push('/a');
  await router.isReady();
});

describe('OTabView — view mode', () => {
  it('renders a tab per OTabPanel with its translated title', () => {
    const wrapper = mount();

    expect(wrapper.text()).toContain('Overview');
    expect(wrapper.text()).toContain('Activity');
  });

  it('renders the panel icon when provided', () => {
    expect(mount().find('i.pi-bolt').exists()).toBe(true);
  });

  it('renders the actions slot in the tab header', () => {
    expect(mount().find('.edit-action').exists()).toBe(true);
  });

  it('renders only the first panel content when no model is set', () => {
    const wrapper = mount();

    expect(wrapper.find('.overview-content').exists()).toBe(true);
    expect(wrapper.find('.activity-content').exists()).toBe(false);
  });

  it('renders the content of the active panel from the model', () => {
    const wrapper = mount({ props: { modelValue: 'activity' } });

    expect(wrapper.find('.activity-content').exists()).toBe(true);
    expect(wrapper.find('.overview-content').exists()).toBe(false);
  });

  it('updates the model when the tab strip changes value', async () => {
    const wrapper = mount({ props: { modelValue: 'overview' } });

    await wrapper.findComponent(tabsStub).vm.$emit('update:value', 'activity');

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['activity']);
  });

  it('does not render a RouterView in view mode', () => {
    expect(mount().findComponent({ name: 'RouterView' }).exists()).toBe(false);
  });
});

describe('OTabView — tips', () => {
  it('renders a tip beside the title when the panel names one', () => {
    const wrapper = mount({ slot: '<OTabPanel title="Overview" tipKey="overview.tip" value="overview" />' });

    expect(wrapper.find('.tip-stub').text()).toBe('overview.tip');
  });

  // The descriptor is read off vnode.props, where Vue leaves a kebab-case attribute as it was written.
  it('reads tip-key written in kebab case', () => {
    const wrapper = mount({ slot: '<OTabPanel title="Overview" tip-key="overview.tip" value="overview" />' });

    expect(wrapper.find('.tip-stub').text()).toBe('overview.tip');
  });

  // PrimeVue's Tab is a button that switches on click; the tip is a button inside it.
  it('keeps a click on the tip from reaching the tab', async () => {
    let tabClicks = 0;
    const wrapper = mount({ slot: '<OTabPanel title="Overview" tipKey="overview.tip" value="overview" />' });

    wrapper.find('.tab-stub').element.addEventListener('click', () => tabClicks++);
    await wrapper.find('.tip-stub').trigger('click');

    expect(tabClicks).toBe(0);
  });

  it('renders no tip when the panel names none', () => {
    const wrapper = mount();

    expect(wrapper.find('.tip-stub').exists()).toBe(false);
  });
});

describe('OTabView — routes mode', () => {
  it('renders the active route through a single RouterView', async () => {
    router.push('/a');
    await router.isReady();

    const wrapper = mount({ props: { mode: 'routes' }, slot: routesSlot });
    await flushPromises();

    expect(wrapper.find('.route-a').exists()).toBe(true);
    expect(wrapper.find('.route-b').exists()).toBe(false);
  });

  it('navigates to the tab route when the strip changes value', async () => {
    router.push('/a');
    await router.isReady();

    const wrapper = mount({ props: { mode: 'routes' }, slot: routesSlot });
    await flushPromises();

    await wrapper.findComponent(tabsStub).vm.$emit('update:value', 'b');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('tab-b');
    expect(wrapper.find('.route-b').exists()).toBe(true);
  });
});
