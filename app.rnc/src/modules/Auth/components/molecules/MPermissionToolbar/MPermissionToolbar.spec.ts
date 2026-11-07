import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MPermissionToolbar from './MPermissionToolbar.vue';

const GLOBAL_OPTIONS = { components: { Button, ToggleSwitch } };

const EDIT_BUTTON = '.p-button-secondary';
const DELETE_BUTTON = '.p-button-danger';

function mountComponent(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MPermissionToolbar, {
    global: GLOBAL_OPTIONS,
    props: { loading: false, modelValue: false, tab: 1, ...props },
  });
}

describe('MPermissionToolbar', () => {
  it('labels the switch "Expand all" while the panels are collapsed', () => {
    const wrapper = mountComponent({ modelValue: false });

    expect(wrapper.find('label').text()).toBe('Expand all');
  });

  it('labels the switch "Collapse all" once the panels are expanded', () => {
    const wrapper = mountComponent({ modelValue: true });

    expect(wrapper.find('label').text()).toBe('Collapse all');
  });

  it('scopes the switch id to the tab so two toolbars never collide', () => {
    const wrapper = mountComponent({ tab: 'manager' });

    // The id belongs on the inner <input>, not on the component root: a <label for> pointing at a
    // <div> does not focus or toggle anything.
    expect(wrapper.find('label').attributes('for')).toBe('toggle-all-panels-manager');
    expect(wrapper.find('input#toggle-all-panels-manager').exists()).toBe(true);
  });

  it('labels the switch for screen readers with the action it performs', () => {
    const wrapper = mountComponent({ modelValue: true });

    expect(wrapper.find('input[type="checkbox"]').attributes('aria-label')).toBe('Collapse all');
  });

  it('emits update:modelValue when the expand-all switch is toggled', async () => {
    const wrapper = mountComponent({ modelValue: false });

    await wrapper.findComponent(ToggleSwitch).find('input').trigger('change');

    expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
  });

  it('emits onSavePermissionsRequest with no payload when save is clicked', async () => {
    const wrapper = mountComponent();

    await wrapper.find('[data-testid="save-permissions"]').trigger('click');

    expect(wrapper.emitted('onSavePermissionsRequest')).toEqual([[]]);
  });

  it('emits onEditProfileRequest when the edit button is clicked', async () => {
    const wrapper = mountComponent();

    await wrapper.find(EDIT_BUTTON).trigger('click');

    expect(wrapper.emitted('onEditProfileRequest')).toEqual([[]]);
  });

  it('emits onDeleteProfileRequest with the click event when delete is clicked', async () => {
    const wrapper = mountComponent();

    await wrapper.find(DELETE_BUTTON).trigger('click');

    const DELETE_EVENTS = wrapper.emitted('onDeleteProfileRequest');

    expect(DELETE_EVENTS).toHaveLength(1);
    expect(DELETE_EVENTS?.[0]?.[0]).toBeInstanceOf(Event);
  });

  it('disables the delete button when the profile cannot be deleted', () => {
    const wrapper = mountComponent({ canDeleteProfile: false });

    expect(wrapper.find(DELETE_BUTTON).attributes('disabled')).toBeDefined();
  });

  it('allows deleting by default', () => {
    const wrapper = mountComponent();

    expect(wrapper.find(DELETE_BUTTON).attributes('disabled')).toBeUndefined();
  });

  // An explicit `:disabled` overrides the one `:loading` implies, so delete stayed clickable
  // while the role was saving — the only destructive control on the row.
  it('disables every control, delete included, while saving', () => {
    const wrapper = mountComponent({ loading: true });

    expect(wrapper.find('input[type="checkbox"]').attributes('disabled')).toBeDefined();
    expect(wrapper.find('[data-testid="save-permissions"]').attributes('disabled')).toBeDefined();
    expect(wrapper.find(EDIT_BUTTON).attributes('disabled')).toBeDefined();
    expect(wrapper.find(DELETE_BUTTON).attributes('disabled')).toBeDefined();
  });

  it('shows the loading spinner on every action button while saving', () => {
    const wrapper = mountComponent({ loading: true });

    expect(wrapper.findAll('button.p-button-loading')).toHaveLength(3);
  });

  /*
   * The screen opens on `permissions.read`, and every write behind these three buttons asks the API
   * for `permissions.update`. A reader was offered save, edit and delete, and each one came back 403.
   */
  it('offers no save, edit or delete to an account that cannot update permissions', () => {
    const wrapper = mountComponent({ canUpdate: false });

    expect(wrapper.find('[data-testid="save-permissions"]').exists()).toBe(false);
    expect(wrapper.find(EDIT_BUTTON).exists()).toBe(false);
    expect(wrapper.find(DELETE_BUTTON).exists()).toBe(false);
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
  });
});
