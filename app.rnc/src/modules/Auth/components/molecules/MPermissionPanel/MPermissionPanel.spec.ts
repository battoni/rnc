import Panel from 'primevue/panel';
import ToggleSwitch from 'primevue/toggleswitch';
import { describe, expect, it } from 'vitest';
import type { PermissionGroup } from '@AuthModule';
import { mountWithPlugins } from '@/test/mount';
import MPermissionPanel from './MPermissionPanel.vue';

const GLOBAL_OPTIONS = { components: { Panel, ToggleSwitch } };

function makePermissionGroup(readValue: boolean, createValue: boolean): PermissionGroup {
  return {
    id: 'group-users',
    nameKey: 'rolesAndPermissions.groups.users',
    icon: 'pi pi-user',
    permissions: [
      { key: 'users.read', labelKey: 'rolesAndPermissions.permissions.read', value: readValue },
      { key: 'users.create', labelKey: 'rolesAndPermissions.permissions.create', value: createValue },
    ],
  };
}

function mountComponent(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MPermissionPanel, {
    global: GLOBAL_OPTIONS,
    props: {
      allPanelsExpanded: false,
      loading: false,
      modelValue: false,
      permissionGroup: makePermissionGroup(true, false),
      ...props,
    },
  });
}

function findSelectAllInput(wrapper: ReturnType<typeof mountComponent>) {
  return wrapper.find('input#toggle-all-group-users');
}

describe('MPermissionPanel', () => {
  it('renders the translated group name and its icon', () => {
    const wrapper = mountComponent();

    expect(wrapper.find('h3').text()).toBe('Users');
    expect(wrapper.find('.p-panel-header i').classes()).toContain('pi-user');
  });

  it('renders one switch per permission plus the select-all switch', () => {
    const wrapper = mountComponent();

    expect(wrapper.findAllComponents(ToggleSwitch)).toHaveLength(3);
  });

  // The id belongs on the inner <input>: on the component root it lands on a <div>, and a
  // <label for> pointing at a div neither focuses nor toggles anything.
  it('points each permission label at the switch input it toggles', () => {
    const wrapper = mountComponent();

    const labels = wrapper.findAll('label');

    expect(labels.map((label) => label.text())).toEqual(['Read', 'Create']);
    expect(
      labels.map((label) => wrapper.find(`input#${label.attributes('for')!.replace('.', '\\.')}`).exists())
    ).toEqual([true, true]);
  });

  // The visible <label for> is the switch's name. An aria-label would override it, and the one this
  // had appended an English " permission" that a Portuguese reader heard too.
  it('names each permission switch by its visible label alone', () => {
    const wrapper = mountComponent();

    expect(wrapper.find('input#users\\.read').attributes('aria-label')).toBeUndefined();
    expect(wrapper.find('input#users\\.create').attributes('aria-label')).toBeUndefined();
    expect(wrapper.find('label[for="users.read"]').text()).toBe('Read');
  });

  it('paints the group icon with the brand ramp, not the raw ink ramp', () => {
    const wrapper = mountComponent();

    expect(wrapper.find('.p-panel-header i').classes()).toContain('text-primary-950');
    expect(wrapper.find('.p-panel-header i').classes()).not.toContain('text-ink-950');
  });

  it('leaves select-all off while at least one permission is off', () => {
    const wrapper = mountComponent();

    expect(findSelectAllInput(wrapper).attributes('aria-checked')).toBe('false');
    expect(findSelectAllInput(wrapper).attributes('aria-label')).toBe('Activate all');
  });

  it('turns select-all on when every permission is on', () => {
    const wrapper = mountComponent({ permissionGroup: makePermissionGroup(true, true) });

    expect(findSelectAllInput(wrapper).attributes('aria-checked')).toBe('true');
    expect(findSelectAllInput(wrapper).attributes('aria-label')).toBe('Deactivate all');
  });

  it('activates every permission of the group when select-all is switched on', async () => {
    const permissionGroup = makePermissionGroup(true, false);
    const wrapper = mountComponent({ permissionGroup });

    await findSelectAllInput(wrapper).trigger('change');

    expect(permissionGroup.permissions.map((permission) => permission.value)).toEqual([true, true]);
  });

  it('deactivates every permission of the group when select-all is switched off', async () => {
    const permissionGroup = makePermissionGroup(true, true);
    const wrapper = mountComponent({ permissionGroup });

    await findSelectAllInput(wrapper).trigger('change');

    expect(permissionGroup.permissions.map((permission) => permission.value)).toEqual([false, false]);
  });

  it('toggles a single permission without touching its siblings', async () => {
    const permissionGroup = makePermissionGroup(true, false);
    const wrapper = mountComponent({ permissionGroup });

    await wrapper.find('input#users\\.create').trigger('change');

    expect(permissionGroup.permissions.map((permission) => permission.value)).toEqual([true, true]);
  });

  it('starts collapsed when no collapsed model is bound', () => {
    const wrapper = mountWithPlugins(MPermissionPanel, {
      global: GLOBAL_OPTIONS,
      props: { allPanelsExpanded: false, loading: false, permissionGroup: makePermissionGroup(true, false) },
    });

    expect(wrapper.find('.p-panel-toggle-button').attributes('aria-expanded')).toBe('false');
  });

  it('emits update:modelValue when the panel header toggle is clicked', async () => {
    const wrapper = mountComponent({ modelValue: false });

    await wrapper.find('.p-panel-toggle-button').trigger('click');

    expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
  });

  it('disables every switch while the role is saving', () => {
    const wrapper = mountComponent({ loading: true });

    const SWITCH_INPUTS = wrapper.findAll('input[type="checkbox"]');

    expect(SWITCH_INPUTS).toHaveLength(3);
    expect(SWITCH_INPUTS.every((input) => input.attributes('disabled') !== undefined)).toBe(true);
  });
});
