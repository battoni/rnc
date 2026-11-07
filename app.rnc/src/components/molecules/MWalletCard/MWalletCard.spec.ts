import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MWalletCard from './MWalletCard.vue';

const cardStub = {
  template: '<div class="card-stub"><slot name="content" /></div>',
};
const popoverStub = {
  props: ['dismissable', 'id'],
  template: '<div class="popover-stub"><slot /></div>',
};

const defaultProps = { title: 'Revenue', value: '$1,000', description: 'Monthly total' };

function mount(props: { title: string; value: string; description: string; variant?: string } = defaultProps) {
  return mountWithPlugins(MWalletCard, {
    props: props as any,
    global: { stubs: { Card: cardStub, Popover: popoverStub } },
  });
}

describe('MWalletCard', () => {
  it('renders the title', () => {
    expect(mount().text()).toContain('Revenue');
  });

  it('renders the value', () => {
    expect(mount().text()).toContain('$1,000');
  });

  it('renders the description', () => {
    expect(mount().text()).toContain('Monthly total');
  });

  it('defaults to the info variant accent class', () => {
    expect(mount().html()).toContain('bg-info-300');
  });

  it('applies primary accent class for primary variant', () => {
    const wrapper = mount({ ...defaultProps, variant: 'primary' });
    expect(wrapper.html()).toContain('bg-primary-500');
  });

  it('applies success accent class for success variant', () => {
    const wrapper = mount({ ...defaultProps, variant: 'success' });
    expect(wrapper.html()).toContain('bg-success-500');
  });

  it('applies warning accent class for warning variant', () => {
    const wrapper = mount({ ...defaultProps, variant: 'warning' });
    expect(wrapper.html()).toContain('bg-warn-200');
  });

  it('applies info title color class by default', () => {
    expect(mount().html()).toContain('text-info-700');
  });

  it('applies primary title color class for primary variant', () => {
    const wrapper = mount({ ...defaultProps, variant: 'primary' });
    expect(wrapper.html()).toContain('text-primary-700');
  });
});
