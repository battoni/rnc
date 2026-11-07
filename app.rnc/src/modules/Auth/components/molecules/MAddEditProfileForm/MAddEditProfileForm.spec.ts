import { defineComponent, h } from 'vue';
import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { flushPromises } from '@vue/test-utils';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Profile } from '@AuthModule';
import { mountWithPlugins } from '@/test/mount';

const { createRoleService, toastAdd, updateRoleService } = vi.hoisted(() => ({
  createRoleService: vi.fn(),
  toastAdd: vi.fn(),
  updateRoleService: vi.fn(),
}));

vi.mock('@AuthModule', () => ({ CreateRoleService: createRoleService, UpdateRoleService: updateRoleService }));
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

import MAddEditProfileForm from './MAddEditProfileForm.vue';

const STUBS = { Form: true, FormField: true, InputText: true, MultiSelect: true, Textarea: true };

function groupOf(id: string, isHeld: boolean) {
  return { icon: 'pi pi-circle', id, isHeld, nameKey: `groups.${id}`, permissions: [] };
}

describe('MAddEditProfileForm', () => {
  // The role endpoint returns every declared group, held or not, so presence no longer means
  // "held": pre-selecting all of them made the picker say the role had groups it did not.
  it('pre-selects only the groups the profile holds', () => {
    const PROFILE = {
      description: '',
      id: 7,
      name: 'Editors',
      permissionGroups: [groupOf('users', false), groupOf('tips', true), groupOf('design', false)],
    } as unknown as Profile;

    const wrapper = mountWithPlugins(MAddEditProfileForm, {
      props: { permissionGroups: [], profile: PROFILE },
      global: { stubs: STUBS },
    });

    expect(
      (wrapper.vm as unknown as { initialValues: { permissionGroups: string[] } }).initialValues.permissionGroups
    ).toEqual(['tips']);
  });

  it('starts empty when creating', () => {
    const wrapper = mountWithPlugins(MAddEditProfileForm, {
      props: { permissionGroups: [] },
      global: { stubs: STUBS },
    });

    expect(
      (wrapper.vm as unknown as { initialValues: { permissionGroups: string[] } }).initialValues.permissionGroups
    ).toEqual([]);
  });

  describe('when the API refuses the save', () => {
    // AFormError reads the route; here only the text it is handed matters.
    const AFormError = defineComponent({
      props: { error: { type: String, default: null } },
      setup: (props) => () => h('p', { 'data-testid': 'form-error' }, props.error ?? ''),
    });
    const PROFILE = {
      description: '',
      id: 3,
      name: 'Editors',
      permissionGroups: [groupOf('tips', true)],
    } as unknown as Profile;

    beforeEach(() => vi.clearAllMocks());

    function mountEditing() {
      return mountWithPlugins(MAddEditProfileForm, {
        props: { permissionGroups: [groupOf('tips', true)], profile: PROFILE },
        global: {
          components: { AFormError, Button, Form, FormField, InputText },
          stubs: { MultiSelect: true, Textarea: true },
        },
      });
    }

    async function submit(wrapper: ReturnType<typeof mountEditing>) {
      await wrapper.find('form').trigger('submit');
      await flushPromises();
      await flushPromises();
    }

    // A 422 names the rule. It goes under the field it is about, with no toast on top (celer-07).
    it('shows a taken name under the name field and raises no toast', async () => {
      updateRoleService.mockRejectedValue({
        response: { status: 422, data: { errors: { name: ['rolesAndPermissions.form.nameTaken'] } } },
      });
      const wrapper = mountEditing();

      await submit(wrapper);

      expect(updateRoleService).toHaveBeenCalledOnce();
      expect(wrapper.get('[data-testid="profile-name-refusal"]').text()).toBe(
        'A profile with this name already exists'
      );
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('shows any other refusal in the form error, still without a toast', async () => {
      updateRoleService.mockRejectedValue({
        response: { status: 422, data: { errors: { permissions: ['rolesAndPermissions.unknownPermission'] } } },
      });
      const wrapper = mountEditing();

      await submit(wrapper);

      expect(wrapper.get('[data-testid="form-error"]').text()).not.toBe('');
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('raises the error toast for a failure that is not a refusal', async () => {
      updateRoleService.mockRejectedValue({ response: { status: 500 } });
      const wrapper = mountEditing();

      await submit(wrapper);

      expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
      expect(wrapper.find('[data-testid="profile-name-refusal"]').exists()).toBe(false);
    });
  });

  // Adjacent controls sit 12px apart at least; at 8px the grown hit areas overlap (VIS-15).
  it('keeps the footer buttons at least gap-3 apart', () => {
    const wrapper = mountWithPlugins(MAddEditProfileForm, {
      props: { permissionGroups: [] },
      global: { stubs: { ...STUBS, AFormError: true } },
    });

    expect(wrapper.get('button[type="submit"]').element.parentElement?.classList).toContain('gap-3');
  });
});
