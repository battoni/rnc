<script setup lang="ts">
import { ref } from 'vue';
import type { SaveFailure } from '../../../interfaces';
import type { User } from '@UserModule';
import { useApiRefusal, useOneTimePassword } from '../../../composables';
import { UpdateTeamMemberService } from '../../../services';

/*
 * A team login's password reset (rnc-4 R31): a new password drawn here (W81), sent alone in the PATCH
 * (`users.reset_password`) and shown once. Nothing in the row changes, so nothing goes to the view.
 * A confirmation that needs no input stays a button inside this form — the dialog is the "are you sure".
 */
const emit = defineEmits<{
  onClose: [];
}>();

const props = defineProps<{
  user: User;
}>();

const { fieldErrorsOf, formErrorOf } = useApiRefusal();
const { generate } = useOneTimePassword();

const error = ref<string | null>(null);
const isSaving = ref(false);
const password = ref<string | null>(null);

// EVENTS
function onGenerate() {
  const PAYLOAD = { password: generate() };

  error.value = null;
  isSaving.value = true;

  UpdateTeamMemberService(props.user.id, PAYLOAD)
    .then(() => (password.value = PAYLOAD.password))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: SaveFailure) {
  error.value = fieldErrorsOf(failure).password ?? formErrorOf(failure);
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[500px] flex-col gap-4">
    <p class="text-body text-sm break-words">
      {{ $t('firmSettings.reset.lede', { name: `${user.name} ${user.last_name}`, username: user.username }) }}
    </p>

    <MOneTimePassword
      v-if="password"
      :password
    />

    <AFormError :error />
  </div>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      v-if="password"
      data-testid="reset-done"
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
        data-testid="reset-generate"
        icon="pi pi-key"
        :label="$t('firmSettings.reset.submit')"
        :loading="isSaving"
        @click="onGenerate"
      />
    </template>
  </div>
</template>
