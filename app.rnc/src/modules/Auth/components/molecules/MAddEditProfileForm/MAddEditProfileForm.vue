<script setup lang="ts">
import { computed, ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import * as yup from 'yup';
import type { PermissionGroup, Profile } from '@AuthModule';
import type { FormSubmitEvent } from '@primevue/forms';
import { HTTP_STATUS } from '@Enums';
import { translateError } from '@Helpers';
import { CreateRoleService, UpdateRoleService } from '@AuthModule';

interface SubmitError {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [role: Profile];
}>();

const props = defineProps<{
  permissionGroups: PermissionGroup[];
  profile?: Profile | null;
}>();

const { t } = useI18n();
const toast = useToast();

const resolver = yupResolver(
  yup.object().shape({
    description: yup.string().optional(),
    name: yup.string().required('rolesAndPermissions.form.nameRequired'),
    permissionGroups: yup.array().min(1, 'rolesAndPermissions.form.permissionGroupsRequired'),
  })
);

const error = ref<string | null>(null);
const loading = ref(false);
const nameRefusal = ref<string | null>(null);

const isEditMode = computed(() => !!props.profile);

const initialValues = computed(() => ({
  description: props.profile?.description ?? '',
  name: props.profile?.name ?? '',
  permissionGroups:
    props.profile?.permissionGroups?.filter((group) => group.isHeld).map((group) => group.id) ?? ([] as string[]),
}));

const permissionGroupOptions = computed(() =>
  props.permissionGroups.map((group) => ({
    icon: group.icon,
    label: t(group.nameKey),
    value: group.id,
  }))
);

// HELPERS
function getErrorMessage(fieldError: unknown): string {
  if (!fieldError) return '';

  if (typeof fieldError === 'string') return t(fieldError);

  if (
    fieldError &&
    typeof fieldError === 'object' &&
    'message' in fieldError &&
    typeof (fieldError as { message: unknown }).message === 'string'
  ) {
    const message = (fieldError as { message: string }).message;
    const key = message.includes(':') ? message.split(':')[1]?.trim() : message;
    return t(key || message);
  }

  return String(fieldError);
}

// EVENTS
function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  loading.value = true;
  error.value = null;
  nameRefusal.value = null;

  const payload = {
    name: values.name,
    description: values.description || undefined,
    permission_group_ids: values.permissionGroups,
  };

  const service = props.profile ? UpdateRoleService(props.profile.id, payload) : CreateRoleService(payload);

  service
    .then(({ data }) => {
      emit('onSuccess', data);
      const successKey = props.profile
        ? 'rolesAndPermissions.roleUpdatedSuccess'
        : 'rolesAndPermissions.roleCreatedSuccess';

      toast.add({
        severity: 'success',
        summary: t(successKey, { name: data.name }),
        life: 5000,
      });
    })
    .catch((submitError: SubmitError) => {
      const isRefusal = submitError?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;

      if (!isRefusal) {
        toast.add({
          severity: 'error',
          summary: t('errors.somethingWentWrong'),
          detail: t('errors.refreshAndContactAdmin'),
          life: 10000,
        });
        return;
      }

      // A 422 names the rule it broke. It is shown where the problem is, and no toast contradicts it.
      const refusals = submitError.response?.data?.errors ?? {};
      const nameKey = refusals.name?.[0];

      if (nameKey) {
        nameRefusal.value = translateError(nameKey);
        return;
      }

      const refusalKey = Object.values(refusals)[0]?.[0];

      error.value = refusalKey ? translateError(refusalKey) : t('errors.somethingWentWrong');
    })
    .finally(() => (loading.value = false));
}
</script>

<template>
  <Form
    id="profile-form"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    :initialValues
    :resolver
    @submit="onSubmit"
  >
    <FormField name="name">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="name"
            :class="['mb-2 block text-sm', fieldError || nameRefusal ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('rolesAndPermissions.form.name') }}
          </label>

          <InputText
            fluid
            id="name"
            name="name"
            :disabled="loading"
            :invalid="nameRefusal ? true : undefined"
            :placeholder="$t('rolesAndPermissions.form.namePlaceholder')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ getErrorMessage(fieldError) }}
          </small>

          <small
            v-else-if="nameRefusal"
            class="text-danger-700 mt-1 block text-sm"
            data-testid="profile-name-refusal"
          >
            {{ nameRefusal }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="description">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="description"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('rolesAndPermissions.form.description') }}
          </label>

          <Textarea
            fluid
            id="description"
            name="description"
            :disabled="loading"
            :placeholder="$t('rolesAndPermissions.form.descriptionPlaceholder')"
            :rows="4"
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

    <FormField name="permissionGroups">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="permissionGroups"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('rolesAndPermissions.form.permissionGroups') }}
          </label>

          <MultiSelect
            filter
            fluid
            display="chip"
            inputId="permissionGroups"
            name="permissionGroups"
            optionLabel="label"
            optionValue="value"
            :disabled="loading"
            :options="permissionGroupOptions"
            :placeholder="$t('rolesAndPermissions.form.permissionGroupsPlaceholder')"
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

    <AFormError :error />
  </Form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('common.cancel')"
      :loading
      @click="emit('onClose')"
    />

    <Button
      form="profile-form"
      type="submit"
      :label="isEditMode ? $t('common.save') : $t('common.create')"
      :loading
    />
  </div>
</template>
