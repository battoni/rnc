import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MConfirmPopup from './MConfirmPopup.vue';

// Provide the container slot with static message data so button behaviour is testable
// without requiring a live ConfirmationService event.
const confirmPopupStub = {
  props: ['group', 'class'],
  template: `
    <div class="confirm-popup-stub" :data-group="group">
      <slot
        name="container"
        :message="{ header: 'Confirm?', message: 'Are you sure?' }"
        :acceptCallback="() => {}"
        :rejectCallback="() => {}"
      />
    </div>
  `,
};

// Button stub that forwards $attrs so @click handlers from the parent reach the element.
const buttonStub = {
  props: ['label', 'disabled', 'loading', 'severity', 'size', 'variant', 'text', 'plain'],
  template: '<button :disabled="disabled || loading" v-bind="$attrs">{{ label }}</button>',
};

function mount(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MConfirmPopup, {
    props: { group: 'test-group', ...props },
    global: { stubs: { ConfirmPopup: confirmPopupStub, Button: buttonStub } },
  });
}

describe('MConfirmPopup', () => {
  it('forwards the group prop to the underlying ConfirmPopup', () => {
    const wrapper = mount({ group: 'delete' });
    expect(wrapper.find('[data-group="delete"]').exists()).toBe(true);
  });

  it('renders the Cancel button', () => {
    const wrapper = mount();
    const cancelBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('cancel'));
    expect(cancelBtn).toBeTruthy();
  });

  it('renders the Delete button by default', () => {
    const wrapper = mount();
    const deleteBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('delete'));
    expect(deleteBtn).toBeTruthy();
  });

  it('uses custom confirmLabel when provided', () => {
    expect(mount({ confirmLabel: 'Archive' }).text()).toContain('Archive');
  });

  it('uses custom cancelLabel when provided', () => {
    expect(mount({ cancelLabel: 'No thanks' }).text()).toContain('No thanks');
  });

  it('emits onAccept with acceptCallback when confirm button is clicked', async () => {
    const wrapper = mount();
    const confirmBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('delete'));
    expect(confirmBtn).toBeTruthy();

    await confirmBtn!.trigger('click');

    const emitted = wrapper.emitted('onAccept');
    expect(emitted).toBeTruthy();

    expect(typeof (emitted as any)[0][0]).toBe('function');
  });

  it('emits onReject with rejectCallback when cancel button is clicked', async () => {
    const wrapper = mount();
    const cancelBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('cancel'));
    expect(cancelBtn).toBeTruthy();

    await cancelBtn!.trigger('click');

    const emitted = wrapper.emitted('onReject');
    expect(emitted).toBeTruthy();

    expect(typeof (emitted as any)[0][0]).toBe('function');
  });

  it('disables the cancel button when loading is true', () => {
    const wrapper = mount({ loading: true });
    const cancelBtn = wrapper.findAll('button').find((btn) => btn.text().toLowerCase().includes('cancel'));
    expect(cancelBtn?.element.disabled).toBe(true);
  });

  /*
   * Two small buttons whose hit areas each grow 6px under a finger (touch-target.css). At `gap-2`
   * they overlapped by 4px, and Delete — the later sibling — won the strip beside Cancel. The
   * preset's `confirmpopup.footer.gap` does not reach a footer this component draws itself.
   */
  it('keeps Cancel and Delete 12px apart', () => {
    const wrapper = mount();
    const footer = wrapper.findAll('button')[0]!.element.parentElement!;

    expect(footer.classList).toContain('gap-3');
  });
});
