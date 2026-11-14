<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type {
  SaveFailure,
  TeamMemberCreatePayload,
  TeamMemberUpdatePayload,
  TeamRoleOption,
} from '../../../interfaces';
import type { User } from '@UserModule';
import { useApiRefusal, useOneTimePassword } from '../../../composables';
import { CreateTeamMemberService, UpdateTeamMemberService } from '../../../services';

/*
 * A team login made or edited by the firm's manager (rnc-4 R31, W76): names, username, one of the two
 * team roles and the specialist flag. The firm is never sent — the API stamps the manager's own. A new
 * login's password is drawn here (W81), sent once and shown once; an edit never touches it (the reset
 * is its own form). A flagged specialist starts on the firm's hours the first time (C17).
 */
type FieldErrors = Partial<Record<'last_name' | 'name' | 'role_id' | 'username', string>>;

const emit = defineEmits<{
  onClose: [];
  onSuccess: [user: User];
}>();

const props = defineProps<{
  roles: TeamRoleOption[];
  user: User | null;
}>();

const { fieldErrorsOf, formErrorOf } = useApiRefusal();
const { t } = useI18n();
const { generate } = useOneTimePassword();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);
const password = ref<string | null>(null);

const form = reactive({
  isSpecialist: Boolean(props.user?.is_specialist),
  lastName: props.user?.last_name ?? '',
  name: props.user?.name ?? '',
  roleId: props.roles.find((role) => role.slug === props.user?.role_slug)?.id ?? null,
  username: props.user?.username ?? '',
});

const isEditMode = computed(() => !!props.user);

watch(() => form.name, onNameChange);
watch(() => form.lastName, onLastNameChange);
watch(() => form.username, onUsernameChange);
watch(() => form.roleId, onRoleChange);

// HELPERS
function validate(): FieldErrors {
  const required = t('firmSettings.errors.required');

  return Object.fromEntries(
    [
      ['name', form.name.trim()],
      ['last_name', form.lastName.trim()],
      ['username', form.username.trim()],
      ['role_id', form.roleId],
    ]
      .filter(([, value]) => !value)
      .map(([field]) => [field, required])
  );
}

function fields() {
  return {
    is_specialist: form.isSpecialist,
    last_name: form.lastName.trim(),
    name: form.name.trim(),
    role_id: form.roleId as number,
    username: form.username.trim().toLowerCase(),
  };
}

// EVENTS
function onNameChange() {
  delete errors.value.name;
}

function onLastNameChange() {
  delete errors.value.last_name;
}

function onUsernameChange() {
  delete errors.value.username;
}

function onRoleChange() {
  delete errors.value.role_id;
}

function onSubmit() {
  formError.value = null;
  errors.value = validate();

  if (Object.keys(errors.value).length > 0) return;

  isSaving.value = true;

  const request = props.user ? update(props.user.id) : create();

  request.catch(onFailed).finally(() => (isSaving.value = false));
}

function create() {
  const PAYLOAD: TeamMemberCreatePayload = { ...fields(), password: generate(), status: 'active' };

  return CreateTeamMemberService(PAYLOAD).then(({ data }) => {
    password.value = PAYLOAD.password;

    emit('onSuccess', data);
  });
}

function update(id: number) {
  const PAYLOAD: TeamMemberUpdatePayload = fields();

  return UpdateTeamMemberService(id, PAYLOAD).then(({ data }) => emit('onSuccess', data));
}

function onFailed(failure: SaveFailure) {
  const fieldErrors = fieldErrorsOf(failure);

  if (Object.keys(fieldErrors).length === 0) {
    formError.value = formErrorOf(failure);
    return;
  }

  errors.value = fieldErrors;
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[500px] flex-col gap-4">
    <template v-if="password">
      <p
        class="text-body text-sm break-words"
        data-testid="member-created"
      >
        {{ $t('firmSettings.team.created', { username: form.username.trim().toLowerCase() }) }}
      </p>

      <MOneTimePassword :password />
    </template>

    <form
      v-else
      novalidate
      id="team-member-form"
      class="flex flex-col gap-4"
      data-testid="member-form"
      @submit.prevent="onSubmit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="member-name"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.name }]"
          >
            {{ $t('firmSettings.team.name') }}
          </label>

          <InputText
            v-model="form.name"
            autofocus
            id="member-name"
            autocomplete="off"
            class="w-full"
            data-testid="member-name"
            :invalid="!!errors.name"
          />

          <AFormError
            data-testid="member-name-error"
            :error="errors.name"
          />
        </div>

        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="member-last-name"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.last_name }]"
          >
            {{ $t('firmSettings.team.lastName') }}
          </label>

          <InputText
            v-model="form.lastName"
            id="member-last-name"
            autocomplete="off"
            class="w-full"
            data-testid="member-last-name"
            :invalid="!!errors.last_name"
          />

          <AFormError :error="errors.last_name" />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label
          for="member-username"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.username }]"
        >
          {{ $t('firmSettings.team.username') }}
        </label>

        <InputText
          v-model="form.username"
          id="member-username"
          autocapitalize="none"
          autocomplete="off"
          class="w-full"
          data-testid="member-username"
          :invalid="!!errors.username"
        />

        <AFormError :error="errors.username" />
      </div>

      <fieldset class="flex flex-col gap-2">
        <legend :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.role_id }]">
          {{ $t('firmSettings.team.role') }}
        </legend>

        <div class="flex flex-wrap gap-3">
          <label
            v-for="role in roles"
            class="border-line-strong text-body touch-target flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm"
            data-testid="member-role-option"
            :key="role.id"
            :for="`member-role-${role.slug}`"
          >
            <RadioButton
              v-model="form.roleId"
              name="member-role"
              :inputId="`member-role-${role.slug}`"
              :value="role.id"
            />

            {{ role.name }}
          </label>
        </div>

        <AFormError
          data-testid="member-role-error"
          :error="errors.role_id"
        />
      </fieldset>

      <div class="flex items-center gap-3">
        <ToggleSwitch
          v-model="form.isSpecialist"
          data-testid="member-specialist"
          inputId="member-specialist"
        />

        <label
          class="text-body text-sm"
          for="member-specialist"
        >
          {{ $t('firmSettings.team.specialist') }}
        </label>
      </div>

      <p class="text-muted text-xs">{{ $t('firmSettings.team.specialistHint') }}</p>

      <p
        v-if="!isEditMode"
        class="text-muted text-xs"
      >
        {{ $t('firmSettings.team.passwordNote') }}
      </p>

      <AFormError :error="formError" />
    </form>
  </div>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      v-if="password"
      data-testid="member-done"
      :label="$t('firmSettings.done')"
      @click="emit('onClose')"
    />

    <template v-else>
      <Button
        severity="secondary"
        :label="$t('firmSettings.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="member-save"
        form="team-member-form"
        type="submit"
        :label="$t(isEditMode ? 'firmSettings.team.save' : 'firmSettings.team.create')"
        :loading="isSaving"
      />
    </template>
  </div>
</template>
