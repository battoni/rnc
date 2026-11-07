import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import PersonalizationView from './PersonalizationView.view.vue';

const layoutStub = { template: '<div class="layout-stub"><slot name="pageHeader" /><slot /></div>' };
const pageHeaderStub = { props: ['title'], template: '<div class="page-header-stub">{{ title }}</div>' };
const cardStub = { template: '<div class="personalization-card-stub" />' };

function mount() {
  return mountWithPlugins(PersonalizationView, {
    global: {
      stubs: {
        TheLayout: layoutStub,
        ThePageHeader: pageHeaderStub,
        MPersonalizationVisualCard: cardStub,
      },
    },
  });
}

describe('PersonalizationView', () => {
  it('renders the page header with the personalization title key', () => {
    const wrapper = mount();

    expect(wrapper.find('.page-header-stub').text()).toBe('personalization.title');
  });

  it('renders the personalization visual card', () => {
    const wrapper = mount();

    expect(wrapper.find('.personalization-card-stub').exists()).toBe(true);
  });

  it('renders subtitle and description copy', () => {
    const wrapper = mount();

    expect(wrapper.text()).toContain('Interface Settings');
    expect(wrapper.text()).toContain('Adjust the interface appearance');
  });
});
