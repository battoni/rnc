// Register PrimeVue components explicitly — auto-import only works in Vite, not Vitest.
import Dialog from 'primevue/dialog';
import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MMainDialog from './MMainDialog.vue';

const dialogStub = {
  props: ['visible', 'showHeader'],
  emits: ['update:visible', 'show'],
  template: `
    <div v-if="visible" class="p-dialog-stub">
      <div v-if="showHeader !== false" class="p-dialog-header-stub">
        <slot name="header" />
      </div>
      <div class="p-dialog-content-stub"><slot /></div>
      <div class="p-dialog-footer-stub"><slot name="footer" /></div>
    </div>
  `,
};

// Button stub that renders its label so footer text is inspectable.
const buttonStub = {
  props: ['label', 'severity', 'variant', 'loading', 'disabled'],
  template: '<button v-bind="$attrs">{{ label }}</button>',
};

function mount(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mountWithPlugins(MMainDialog, {
    props: { visible: true, ...props },
    slots,
    global: { stubs: { Dialog: dialogStub, Button: buttonStub } },
  });
}

describe('MMainDialog', () => {
  it('renders nothing when visible is false', () => {
    const wrapper = mountWithPlugins(MMainDialog, {
      props: { visible: false },
      global: { stubs: { Dialog: dialogStub, Button: buttonStub } },
    });
    expect(wrapper.find('.p-dialog-stub').exists()).toBe(false);
  });

  it('renders the dialog content when visible is true', () => {
    expect(mount().find('.p-dialog-stub').exists()).toBe(true);
  });

  it('renders the translated title when the title prop is provided', () => {
    expect(mount({ title: 'common.actions.cancel' }).text()).toContain('Cancel');
  });

  it('renders default slot content inside the dialog', () => {
    const wrapper = mount({}, { default: '<p class="slot-content">Hello</p>' });
    expect(wrapper.find('.slot-content').exists()).toBe(true);
  });

  it('renders footer buttons when isFooterless is false', () => {
    const wrapper = mount({ isFooterless: false });
    expect(wrapper.find('.p-dialog-footer-stub').findAll('button').length).toBeGreaterThan(0);
  });

  it('renders no footer buttons when isFooterless is true', () => {
    const wrapper = mount({ isFooterless: true });
    expect(wrapper.find('.p-dialog-footer-stub').findAll('button').length).toBe(0);
  });

  it('hides the header when isHeadless is true', () => {
    expect(mount({ isHeadless: true }).find('.p-dialog-header-stub').exists()).toBe(false);
  });

  it('emits onClose when the cancel button is clicked', async () => {
    const wrapper = mount({ isFooterless: false });
    const cancelBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('cancel'));

    // No guard: a missing button must fail here, not skip the assertion.
    expect(cancelBtn).toBeTruthy();

    await cancelBtn!.trigger('click');

    expect(wrapper.emitted('onClose')).toBeTruthy();
  });

  it('emits onSubmit when the submit button is clicked', async () => {
    const wrapper = mount({ isFooterless: false });
    const submitBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('submit'));

    expect(submitBtn).toBeTruthy();

    await submitBtn!.trigger('click');

    expect(wrapper.emitted('onSubmit')).toBeTruthy();
  });

  // Two default-size buttons side by side: 12px apart, the touch floor between controls (celer-06).
  it('keeps the default footer buttons 12px apart', () => {
    const footerRow = mount({ isFooterless: false }).find('.p-dialog-footer-stub').find('div');

    expect(footerRow.classes()).toContain('gap-3');
  });

  /*
   * The content scrolls with the app's brand scrollbar, `.scrollbar-brand`
   * (`styles/components/scrollbar.css`) — the class, not a private copy of its rules that drifts.
   */
  it('scrolls its content with the shared brand scrollbar', () => {
    const wrapper = mountWithPlugins(MMainDialog, {
      attachTo: document.body,
      props: { visible: true },
      global: { components: { Dialog }, stubs: { Button: buttonStub } },
    });

    expect(wrapper.find('.p-dialog-content').classes()).toContain('scrollbar-brand');

    wrapper.unmount();
  });
});
