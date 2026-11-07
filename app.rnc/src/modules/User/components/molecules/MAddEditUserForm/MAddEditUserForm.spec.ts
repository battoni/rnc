import { defineComponent, h } from 'vue';
import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { flushPromises } from '@vue/test-utils';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Password from 'primevue/password';
import Select from 'primevue/select';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { User } from '../../../interfaces';
import { mountWithPlugins } from '@/test/mount';

const { checkUsernameService, toastAdd, updateUserService } = vi.hoisted(() => ({
  checkUsernameService: vi.fn(),
  toastAdd: vi.fn(),
  updateUserService: vi.fn(),
}));

vi.mock('../../../services', () => ({
  CheckUsernameService: checkUsernameService,
  CreateUserService: vi.fn(),
  UpdateUserService: updateUserService,
}));

vi.mock('@AuthModule', () => ({
  GetPermissionGroupsService: vi.fn().mockResolvedValue({ data: [] }),
  GetRolesService: vi.fn().mockResolvedValue({ data: [{ id: 2, name: 'Admin', permissionGroups: [] }] }),
}));

vi.mock('@UserModule', async () => {
  const { defineStore } = await import('pinia');

  return {
    useUserStore: defineStore('user-form-spec', {
      state: () => ({ permissions: [] as string[], user: null as User | null }),
      actions: { fetchMe: () => Promise.resolve() },
    }),
  };
});

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

import MAddEditUserForm from './MAddEditUserForm.vue';

const USER = {
  id: 9,
  last_name: 'Lima',
  name: 'Ana',
  permissions: [],
  role: 'Admin',
  status: 'active',
  username: 'ana',
} as unknown as User;

// AFormError reads the route to build its report; here only the text it is handed matters.
const AFormError = defineComponent({
  props: { error: { type: String, default: null } },
  setup: (props) => () => h('p', { 'data-testid': 'form-error' }, props.error ?? ''),
});

function mountForm() {
  return mountWithPlugins(MAddEditUserForm, {
    props: { user: USER },
    global: { components: { AFormError, Button, Form, FormField, InputText, MultiSelect, Password, Select } },
  });
}

function refusal(status: number, errors?: Record<string, string[]>) {
  return { response: { status, data: errors ? { errors } : {} } };
}

async function submit(wrapper: ReturnType<typeof mountForm>) {
  await wrapper.find('form').trigger('submit');
  await flushPromises();
  await flushPromises();
}

async function typeUsername(wrapper: ReturnType<typeof mountForm>, username: string) {
  // The form remounts once on mount (formResetKey), so the input is looked up after that settles.
  await flushPromises();

  const input = wrapper.find('input#username');

  await input.trigger('focus');
  await input.setValue(username);
  vi.advanceTimersByTime(400);
  await flushPromises();
}

function submitButton(wrapper: ReturnType<typeof mountForm>) {
  return wrapper.get('button[type="submit"]');
}

describe('MAddEditUserForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => vi.useRealTimers());

  describe('when the API refuses the save', () => {
    // A 422 is explained inline, where the problem is; a generic toast on top contradicts it (chapaja-220).
    it('shows a 422 inline, translated, and raises no toast', async () => {
      updateUserService.mockRejectedValue(refusal(422, { username: ['users.form.usernameTaken'] }));
      const wrapper = mountForm();
      await flushPromises();

      await submit(wrapper);

      expect(updateUserService).toHaveBeenCalledOnce();
      expect(wrapper.text()).toContain('This username is already in use');
      expect(toastAdd).not.toHaveBeenCalled();
    });

    it('raises the error toast for anything that is not a 422', async () => {
      updateUserService.mockRejectedValue(refusal(500));
      const wrapper = mountForm();
      await flushPromises();

      await submit(wrapper);

      expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
    });
  });

  describe('the username availability check', () => {
    beforeEach(() => vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] }));

    it('says the username is taken on a 409 and blocks the save', async () => {
      checkUsernameService.mockRejectedValue(refusal(409));
      const wrapper = mountForm();

      await typeUsername(wrapper, 'taken');

      expect(checkUsernameService).toHaveBeenCalledWith({ excludeId: 9, username: 'taken' });
      expect(wrapper.text()).toContain('This username is already in use');
      expect(submitButton(wrapper).attributes('disabled')).toBeDefined();
    });

    // A 403 (or a network failure) means the check did not run — not that the name is free.
    it('says the check could not run when it is refused, without calling the name taken', async () => {
      checkUsernameService.mockRejectedValue(refusal(403));
      const wrapper = mountForm();

      await typeUsername(wrapper, 'someone');

      expect(wrapper.get('[data-testid="username-check-unavailable"]').text()).toBe(
        'Could not check this username now. It will be checked when you save.'
      );
      expect(wrapper.text()).not.toContain('This username is already in use');
      expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('clears the notice once a later check answers', async () => {
      checkUsernameService.mockRejectedValueOnce(refusal(403)).mockResolvedValueOnce({ data: { available: true } });
      const wrapper = mountForm();

      await typeUsername(wrapper, 'someone');
      await typeUsername(wrapper, 'someone2');

      expect(wrapper.find('[data-testid="username-check-unavailable"]').exists()).toBe(false);
    });
  });

  // Adjacent controls sit 12px apart at least; at 8px the grown hit areas overlap (VIS-15).
  it('keeps the footer buttons at least gap-3 apart', () => {
    const wrapper = mountForm();

    expect(submitButton(wrapper).element.parentElement?.classList).toContain('gap-3');
  });
});
