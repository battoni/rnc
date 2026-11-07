<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { storeToRefs } from 'pinia';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import * as yup from 'yup';
import type { User } from '../../../interfaces';
import type { UserStatus } from '../../../types';
import type { PermissionGroup, Profile } from '@AuthModule';
import type { FormSubmitEvent } from '@primevue/forms';
import { HTTP_STATUS } from '@Enums';
import { translateError } from '@Helpers';
import { GetPermissionGroupsService, GetRolesService } from '@AuthModule';
import { useUserStore } from '@UserModule';
import { USER_STATUS } from '../../../enums';
import { CheckUsernameService, CreateUserService, UpdateUserService } from '../../../services';

interface SubmitError {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [user: User];
}>();

const props = defineProps<{
  user?: User | null;
}>();

const userStore = useUserStore();
const { permissions: sessionPermissions } = storeToRefs(userStore);

const { t } = useI18n();
const toast = useToast();

let usernameCheckTimeout: ReturnType<typeof setTimeout> | null = null;

const error = ref<string | null>(null);
const formResetKey = ref(0);
const loading = ref(false);
const passwordReadonly = ref(true);
const permissionGroups = ref<PermissionGroup[]>([]);
const roleProfiles = ref<Profile[]>([]);
const selectedPermissions = ref<string[]>([]);
const selectedRoleName = ref('');
const shouldValidateUsername = ref(false);
const usernameCheckUnavailable = ref(false);
const usernameChecking = ref(false);
const usernameError = ref<string | null>(null);

const canResetPassword = computed(() => sessionPermissions.value.includes('users.reset_password'));
const isEditMode = computed(() => !!props.user);
// Editing your own account never changes its status: the API refuses self-deactivation, and a
// field that looks editable and then fails on save is worse than a field that says why it cannot.
const isSelf = computed(() => isEditMode.value && props.user?.id === userStore.user?.id);
const passwordAutocomplete = computed(() => 'new-password');
const resolver = computed(() => yupResolver(schema.value));
const roleOptions = computed(() => roleProfiles.value.map(({ name }) => ({ label: name, value: name })));
const usernameAutocomplete = computed(() => (isEditMode.value ? 'off' : 'new-password'));

const groupedPermissionOptions = computed(() =>
  permissionGroups.value.map(({ nameKey, permissions }) => ({
    items: permissions.map(({ key, labelKey }) => ({ key, label: t(labelKey) })),
    label: t(nameKey),
  }))
);

const initialValues = computed(() => ({
  last_name: props.user?.last_name ?? '',
  name: props.user?.name ?? '',
  password: '',
  password_confirmation: '',
  role: props.user?.role ?? '',
  status: props.user?.status ?? USER_STATUS.ACTIVE,
  username: props.user?.username ?? '',
}));

const schema = computed(() =>
  yup.object().shape({
    last_name: yup.string().required('users.form.lastNameRequired'),
    name: yup.string().required('users.form.nameRequired'),
    password: props.user
      ? yup.string().nullable()
      : yup.string().required('users.form.passwordRequired').min(8, 'users.form.passwordMin'),
    password_confirmation: props.user
      ? yup.string().nullable()
      : yup
          .string()
          .required('users.form.passwordConfirmationRequired')
          .oneOf([yup.ref('password')], 'users.form.passwordMatch'),
    role: yup.string().required('users.form.roleRequired'),
    status: yup.string().required('users.form.statusRequired'),
    username: yup.string().required('users.form.usernameRequired'),
  })
);

const statusOptions = computed(() => [
  { label: t('common.active'), value: USER_STATUS.ACTIVE },
  { label: t('common.inactive'), value: USER_STATUS.INACTIVE },
]);

watch(canResetPassword, onResetPasswordPermissionChange);

onMounted(onComponentMount);

// HELPERS
function buildPermissionsRecord(keys: string[]): Record<string, boolean> {
  const record: Record<string, boolean> = {};

  permissionGroups.value.forEach(({ permissions }) => {
    permissions.forEach(({ key }) => {
      record[key] = keys.includes(key);
    });
  });

  return record;
}

function getErrorMessage(fieldError: unknown): string {
  if (!fieldError) return '';

  if (typeof fieldError === 'string') return t(fieldError);

  //  TODO-ID-47 :: Double check this
  const validFieldError =
    fieldError &&
    typeof fieldError === 'object' &&
    'message' in fieldError &&
    typeof (fieldError as { message: unknown }).message === 'string';

  if (validFieldError) {
    const message = (fieldError as { message: string }).message;
    const key = message.includes(':') ? message.split(':')[1]?.trim() : message;

    return translateError(key || message);
  }

  return String(fieldError);
}

function getPermissionKeysFromProfile(profile: Profile): string[] {
  const keys: string[] = [];

  (profile.permissionGroups ?? []).forEach(({ permissions }) => {
    permissions.forEach(({ key, value }) => {
      if (value) keys.push(key);
    });
  });

  return keys;
}

function getRoleId(roleName: string): number | null {
  const roleProfile = roleProfiles.value.find((profile) => profile.name === roleName);
  if (!roleProfile) return null;

  const parsedRoleId = Number(roleProfile.id);
  return Number.isNaN(parsedRoleId) ? null : parsedRoleId;
}

function loadPermissionGroups() {
  GetPermissionGroupsService()
    .then(({ data }) => (permissionGroups.value = data ?? []))
    .catch(() => {
      permissionGroups.value = [];

      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        life: 5000,
      });
    });
}

function loadRoles() {
  GetRolesService()
    .then(({ data }) => (roleProfiles.value = data ?? []))
    .catch(() => {
      roleProfiles.value = [];

      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        life: 5000,
      });
    });
}

function scheduleUsernameCheck(username: string) {
  if (usernameCheckTimeout) clearTimeout(usernameCheckTimeout);

  usernameError.value = null;
  usernameCheckUnavailable.value = false;
  if (!username || username.length < 2) return;

  usernameCheckTimeout = setTimeout(() => {
    usernameChecking.value = true;

    CheckUsernameService({
      excludeId: props.user?.id,
      username,
    })
      .then((res) => (usernameError.value = res?.data?.available ? null : t('users.form.usernameTaken')))
      .catch((checkError: SubmitError) => {
        const isTaken = checkError?.response?.status === HTTP_STATUS.CONFLICT;

        if (isTaken) {
          usernameError.value = t('users.form.usernameTaken');
          return;
        }

        // A refused check (403, a network failure) did not run — it says nothing about the name.
        // Saying so keeps the save open; the API still refuses a taken name on submit, with a 422.
        usernameCheckUnavailable.value = true;
      })
      .finally(() => (usernameChecking.value = false));
  }, 400);
}

// EVENTS
function onComponentMount() {
  error.value = null;
  formResetKey.value += 1;
  passwordReadonly.value = true;
  shouldValidateUsername.value = false;
  usernameCheckUnavailable.value = false;
  usernameError.value = null;
  selectedRoleName.value = props.user?.role ?? '';
  selectedPermissions.value = [...(props.user?.permissions ?? [])];

  loadPermissionGroups();
  loadRoles();
}

function onPasswordFocus() {
  passwordReadonly.value = false;
}

function onResetPasswordPermissionChange(hasPermission: boolean) {
  const shouldResetPasswordField = isEditMode.value && !hasPermission;
  if (!shouldResetPasswordField) return;

  passwordReadonly.value = true;
  formResetKey.value += 1;
}

function onRoleSelect(roleName: string) {
  selectedRoleName.value = roleName;

  const profile = roleProfiles.value.find((roleProfile) => roleProfile.name === roleName);
  if (!profile) return;

  selectedPermissions.value = getPermissionKeysFromProfile(profile);
}

function onSubmit(event: FormSubmitEvent) {
  const { valid, values: eventValues, states } = event;
  //  TODO-ID-47 :: Double check this
  const values =
    eventValues ??
    (states && typeof states === 'object'
      ? Object.fromEntries(
          Object.entries(states).map(([fieldKey, fieldState]) => [fieldKey, (fieldState as { value?: unknown })?.value])
        )
      : {});

  const hasFormError = !valid || usernameError.value;
  if (hasFormError) return;

  const selectedRole = selectedRoleName.value || values.role;
  const roleName = String(selectedRole ?? '');

  if (!roleName) {
    error.value = t('users.form.roleRequired');
    return;
  }

  // A name the catalogue does not know is a different failure from an empty field: saying "role is
  // required" over a filled-in field sends the person looking for the wrong problem. It happens when
  // the roles request failed and the catalogue came back empty.
  const roleId = getRoleId(roleName);

  if (!roleId) {
    error.value = t('users.form.roleUnknown');
    return;
  }

  loading.value = true;
  error.value = null;

  const payload = {
    last_name: values.last_name,
    name: values.name,
    permissions: buildPermissionsRecord(selectedPermissions.value),
    role_id: roleId,
    status: values.status as UserStatus,
    username: values.username,
  };

  const promise = !isEditMode.value
    ? CreateUserService({ ...payload, password: values.password })
    : UpdateUserService(props.user!.id, {
        ...payload,
        password: !canResetPassword.value ? undefined : values.password || undefined,
      });

  promise
    .then(async ({ data }) => {
      const isEditingCurrentUser = userStore.user != null && data.id === userStore.user.id;
      if (isEditingCurrentUser) {
        await userStore.fetchMe().catch(() =>
          toast.add({
            severity: 'error',
            summary: t('errors.somethingWentWrong'),
            life: 5000,
          })
        );
      }

      emit('onSuccess', data);

      const successKey = isEditMode.value ? 'users.userUpdatedSuccess' : 'users.userCreatedSuccess';

      toast.add({
        severity: 'success',
        summary: t(successKey, { name: data.name }),
        life: 5000,
      });
    })
    .catch((submitError: SubmitError) => {
      const refusalKey = Object.values(submitError?.response?.data?.errors ?? {})[0]?.[0];

      error.value = refusalKey
        ? translateError(refusalKey)
        : (submitError?.translatedMessage ?? t('errors.unexpected'));

      // A 422 is already explained inline, where the problem is. A generic "something went wrong"
      // on top of it contradicts the specific message the person is reading.
      const isValidationError = submitError?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;

      if (isValidationError) return;

      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      });
    })
    .finally(() => (loading.value = false));
}

function onUsernameFocus() {
  shouldValidateUsername.value = true;
}

function onUsernameInput(value: string | undefined) {
  if (!shouldValidateUsername.value) return;

  scheduleUsernameCheck(value ?? '');
}
</script>

<template>
  <Form
    id="user-form"
    autocomplete="off"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    :key="formResetKey"
    :initialValues
    :resolver
    @submit="onSubmit"
  >
    <div class="hidden">
      <input
        autocomplete="username"
        name="username"
        tabindex="-1"
        type="text"
      />

      <input
        autocomplete="current-password"
        name="password"
        tabindex="-1"
        type="password"
      />
    </div>

    <FormField name="name">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="name"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('users.form.name') }}
          </label>

          <InputText
            fluid
            id="name"
            name="name"
            :placeholder="$t('users.form.namePlaceholder')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="last_name">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="last_name"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('users.form.lastName') }}
          </label>

          <InputText
            fluid
            id="last_name"
            name="last_name"
            :placeholder="$t('users.form.lastNamePlaceholder')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="username">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="username"
            :class="['mb-2 block text-sm', fieldError || usernameError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('users.form.username') }}
          </label>

          <div class="flex items-center gap-2">
            <InputText
              fluid
              id="username"
              name="username"
              :autocomplete="usernameAutocomplete"
              :placeholder="$t('users.form.usernamePlaceholder')"
              @focus="onUsernameFocus"
              @update:modelValue="onUsernameInput"
            />

            <i
              v-if="usernameChecking"
              class="pi pi-spin pi-spinner text-muted"
            />
          </div>

          <small
            v-if="fieldError || usernameError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) || usernameError }}
          </small>

          <small
            v-else-if="usernameCheckUnavailable"
            class="text-muted mt-1 block text-sm"
            data-testid="username-check-unavailable"
          >
            {{ $t('users.form.usernameCheckUnavailable') }}
          </small>
        </div>
      </template>
    </FormField>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="role"
      >
        {{ $t('users.form.role') }}
      </label>

      <Select
        fluid
        inputId="role"
        optionLabel="label"
        optionValue="value"
        :modelValue="selectedRoleName"
        :options="roleOptions"
        :placeholder="$t('users.form.rolePlaceholder')"
        @update:modelValue="onRoleSelect"
      />

      <input
        name="role"
        type="hidden"
        :value="selectedRoleName"
      />
    </div>

    <FormField name="status">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="status"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('users.status') }}
          </label>

          <Select
            fluid
            inputId="status"
            name="status"
            optionLabel="label"
            optionValue="value"
            :disabled="isSelf"
            :options="statusOptions"
            :placeholder="$t('users.form.statusPlaceholder')"
          />

          <small
            v-if="isSelf"
            class="text-muted mt-1 block text-sm"
          >
            {{ $t('users.form.cannotChangeOwnStatus') }}
          </small>

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="password">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="password"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('users.form.password') }}

            <span
              v-if="isEditMode && canResetPassword"
              class="text-muted font-normal"
            >
              ({{ $t('users.form.optional') }})
            </span>
          </label>

          <Password
            fluid
            toggleMask
            inputId="password"
            name="password"
            :disabled="isEditMode && !canResetPassword"
            :feedback="!isEditMode"
            :inputProps="{ autocomplete: passwordAutocomplete }"
            :mediumLabel="$t('common.password.mediumLabel')"
            :placeholder="$t('users.form.passwordPlaceholder')"
            :promptLabel="$t('common.password.promptLabel')"
            :readonly="isEditMode && passwordReadonly"
            :strongLabel="$t('common.password.strongLabel')"
            :weakLabel="$t('common.password.weakLabel')"
            @focus="onPasswordFocus"
          />

          <small
            v-if="isEditMode && !canResetPassword"
            class="text-muted mt-1 block text-sm"
          >
            {{ $t('users.form.passwordResetPermissionRequired') }}
          </small>

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField
      v-if="!isEditMode"
      name="password_confirmation"
    >
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="password_confirmation"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('users.form.passwordConfirmation') }}
          </label>

          <Password
            fluid
            toggleMask
            inputId="password_confirmation"
            name="password_confirmation"
            :feedback="false"
            :inputProps="{ autocomplete: passwordAutocomplete }"
            :placeholder="$t('users.form.passwordConfirmationPlaceholder')"
            :promptLabel="$t('common.password.promptLabel')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) }}
          </small>
        </div>
      </template>
    </FormField>

    <div class="border-line flex flex-col gap-2 border-t pt-4">
      <label
        class="text-muted mb-2 block text-sm"
        for="permissions-multiselect"
      >
        {{ $t('users.form.permissions') }}
      </label>

      <MultiSelect
        v-model="selectedPermissions"
        filter
        class="w-full"
        display="chip"
        inputId="permissions-multiselect"
        optionGroupChildren="items"
        optionGroupLabel="label"
        optionLabel="label"
        optionValue="key"
        :emptyFilterMessage="$t('common.multiselect.emptyFilterMessage')"
        :emptyMessage="$t('common.multiselect.emptyMessage')"
        :filterPlaceholder="$t('common.multiselect.filterPlaceholder')"
        :maxSelectedLabels="3"
        :options="groupedPermissionOptions"
        :placeholder="$t('users.form.permissions')"
      />
    </div>

    <AFormError :error />
  </Form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('users.form.cancel')"
      @click="emit('onClose')"
    />

    <Button
      form="user-form"
      type="submit"
      :disabled="!!usernameError"
      :label="isEditMode ? $t('users.form.edit') : $t('users.form.create')"
      :loading
    />
  </div>

  <div class="bg-canvas absolute bottom-0 left-0 z-10 h-5 w-full" />
</template>
