<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FirmManagerPayload, ManagerLogin, PlatformFirm } from '../../../interfaces';
import { translateError } from '@Helpers';
import { PLATFORM_ERROR_KEYS } from '../../../constants';
import { ProvideFirmManagerService } from '../../../services';

/*
 * The firm manager's login from the console (rnc-4 R25, W54): the username of the firm's manager
 * gets a new password (its sessions end); a free username becomes the firm's new manager login;
 * anyone else's is refused on the field and left as it was. The password is answered once and lives
 * only in this form until it closes. Nothing in the firm's row changes, so nothing goes to the view.
 */
type FieldErrors = Partial<Record<keyof FirmManagerPayload, string>>;

interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
}>();

const props = defineProps<{
  firm: PlatformFirm;
}>();

const { t } = useI18n();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);
const login = ref<ManagerLogin | null>(null);

const form = reactive({ email: '', username: '' });

watch(() => form.username, onUsernameChange);
watch(() => form.email, onEmailChange);

// EVENTS
function onUsernameChange() {
  delete errors.value.username;
}

function onEmailChange() {
  delete errors.value.email;
}

function onSubmit() {
  formError.value = null;
  errors.value = form.username.trim() ? {} : { username: t('platform.errors.required') };

  if (errors.value.username) return;

  const PAYLOAD: FirmManagerPayload = {
    email: form.email.trim().toLowerCase() || null,
    username: form.username.trim().toLowerCase(),
  };

  isSaving.value = true;

  ProvideFirmManagerService(props.firm.id, PAYLOAD)
    .then((response) => (login.value = response.data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: SaveError) {
  const fieldErrors = failure.response?.data?.errors ?? {};
  const fields = Object.keys(fieldErrors);

  if (fields.length === 0) {
    formError.value = failure.translatedMessage ?? translateError('errors.unexpected');
    return;
  }

  errors.value = Object.fromEntries(
    fields.map((field) => {
      const code = fieldErrors[field]?.[0] ?? '';
      const key = PLATFORM_ERROR_KEYS[code];

      return [field, key ? t(key) : translateError(code)];
    })
  );
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[500px] flex-col gap-4">
    <template v-if="login">
      <p
        class="text-body text-sm break-words"
        data-testid="manager-result"
      >
        {{
          $t(login.created ? 'platform.manager.created' : 'platform.manager.reset', {
            name: firm.name,
            username: login.manager.username,
          })
        }}
      </p>

      <MOneTimePassword :password="login.password" />
    </template>

    <form
      v-else
      novalidate
      id="firm-manager-form"
      class="flex flex-col gap-4"
      data-testid="manager-form"
      @submit.prevent="onSubmit"
    >
      <p class="text-body text-sm">{{ $t('platform.manager.lede', { name: firm.name }) }}</p>

      <div class="flex flex-col gap-2">
        <label
          for="firm-manager-username"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.username }]"
        >
          {{ $t('platform.manager.username') }}
        </label>

        <InputText
          v-model="form.username"
          autofocus
          id="firm-manager-username"
          autocapitalize="none"
          autocomplete="off"
          class="w-full"
          data-testid="manager-username"
          :invalid="!!errors.username"
        />

        <p class="text-muted text-xs">{{ $t('platform.manager.usernameHint') }}</p>

        <AFormError
          data-testid="manager-username-error"
          :error="errors.username"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label
          for="firm-manager-email"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.email }]"
        >
          {{ $t('platform.manager.email') }}
        </label>

        <InputText
          v-model="form.email"
          id="firm-manager-email"
          class="w-full"
          data-testid="manager-email"
          inputmode="email"
          type="email"
          :invalid="!!errors.email"
        />

        <AFormError :error="errors.email" />
      </div>

      <AFormError :error="formError" />
    </form>
  </div>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      v-if="login"
      data-testid="manager-done"
      :label="$t('platform.form.done')"
      @click="emit('onClose')"
    />

    <template v-else>
      <Button
        severity="secondary"
        :label="$t('platform.form.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="manager-save"
        form="firm-manager-form"
        type="submit"
        :label="$t('platform.manager.submit')"
        :loading="isSaving"
      />
    </template>
  </div>
</template>
