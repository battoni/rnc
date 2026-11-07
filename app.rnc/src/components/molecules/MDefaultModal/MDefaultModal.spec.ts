import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MDefaultModal from './MDefaultModal.vue';

// Stub Drawer: render the container slot with a no-op closeCallback so the
// component's layout logic (wrapperClass / panelClass) is exercised.
const drawerStub = {
  props: ['visible', 'position', 'class', 'closeOnEscape', 'dismissable', 'showCloseIcon', 'modal'],
  emits: ['update:visible'],
  template: `
    <div v-if="visible" class="drawer-stub">
      <slot name="container" :closeCallback="() => {}" />
    </div>
  `,
};

const buttonStub = {
  props: ['icon', 'plain', 'text'],
  template: '<button class="close-button-stub" v-bind="$attrs" />',
};

function mount(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mountWithPlugins(MDefaultModal, {
    props: { visible: true, ...props },
    slots,
    global: { stubs: { Drawer: drawerStub, Button: buttonStub } },
  });
}

describe('MDefaultModal', () => {
  it('renders nothing when visible is false', () => {
    const wrapper = mount({ visible: false });
    expect(wrapper.find('.drawer-stub').exists()).toBe(false);
  });

  it('renders the drawer content when visible is true', () => {
    const wrapper = mount();
    expect(wrapper.find('.drawer-stub').exists()).toBe(true);
  });

  it('renders default slot content', () => {
    const wrapper = mount({}, { default: '<p class="modal-content">Hello</p>' });
    expect(wrapper.find('.modal-content').exists()).toBe(true);
  });

  it('renders header slot when provided', () => {
    const wrapper = mount({}, { header: '<h2 class="modal-header">Title</h2>' });
    expect(wrapper.find('.modal-header').exists()).toBe(true);
  });

  it('renders footer slot when provided', () => {
    const wrapper = mount({}, { footer: '<div class="modal-footer">Footer</div>' });
    expect(wrapper.find('.modal-footer').exists()).toBe(true);
  });

  it('shows the close button when closable is true (default)', () => {
    const wrapper = mount();
    expect(wrapper.find('.close-button-stub').exists()).toBe(true);
  });

  it('hides the close button when closable is false', () => {
    const wrapper = mount({ closable: false });
    expect(wrapper.find('.close-button-stub').exists()).toBe(false);
  });

  it('passes rounded-xl panel class for center position (default)', () => {
    const wrapper = mount();
    expect(wrapper.html()).toContain('rounded-xl');
  });

  it('passes full-width panel class for bottom position', () => {
    const wrapper = mount({ position: 'bottom' });
    expect(wrapper.html()).toContain('w-full');
    expect(wrapper.html()).not.toContain('rounded-xl');
  });

  it('names the icon-only close button for a screen reader', () => {
    expect(mount({ closable: true }).find('.close-button-stub').attributes('aria-label')).toBe('Close');
  });
});
