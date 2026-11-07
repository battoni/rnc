<script setup lang="ts">
import { computed, onBeforeMount, ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import * as yup from 'yup';
import type { LoginCodeApiError } from '@AuthModule';
import type { FormSubmitEvent } from '@primevue/forms';
import { useCountdown } from '@Composables';
import { HTTP_STATUS } from '@Enums';
import { getLoginPath } from '@Helpers';
import { RequestEmailOtpService, SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS, VerifyEmailService } from '@AuthModule';
import { USER_STATUS, useUserStore } from '@UserModule';

const { setUserAndPermissions } = useUserStore();

const toast = useToast();
const { query } = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const {
  isRunning: isResendCoolingDown,
  remainingSeconds: resendCooldownSeconds,
  start: startResendCooldown,
} = useCountdown();

const INITIAL_VALUES = { code: '' };

const isLoading = ref(false);
const isResending = ref(false);
// What the SERVER answered. The form resolver only knows the shape of a code; whether it
// is the right one is the API's call, and that answer has to land under the field — or the
// screen stays silent in its commonest case.
const serverError = ref('');

const email = computed(() => String(query.email ?? ''));

const resolver = yupResolver(
  yup.object().shape({
    code: yup
      .string()
      .trim()
      .required(t('auth.flow.signUp.verify.codeRequired'))
      .matches(/^\d{6}$/, t('auth.flow.signUp.verify.codeFormat')),
  })
);

// Arriving here means a code has just gone out, so the wait starts before the first frame.
onBeforeMount(onCooldownRestart);

// EVENTS
/**
 * By the wall clock (`useCountdown`): the person is in their mail app while this runs, and a
 * phone suspends the timers of a page it is not showing.
 */
function onCooldownRestart() {
  startResendCooldown(SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS);
}

function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  isLoading.value = true;
  serverError.value = '';

  VerifyEmailService({ code: String(values.code ?? '').trim(), email: email.value })
    .then(({ data }) => {
      /*
       * A product that parks new accounts for approval gets the account back WITHOUT a session.
       * Putting it in the store and going home would bounce off the guard and land on the login
       * screen with no explanation; this says what happens next instead.
       */
      const isAwaitingApproval = data.status === USER_STATUS.INACTIVE;

      if (isAwaitingApproval) {
        toast.add({ severity: 'info', summary: t('auth.flow.signUp.verify.awaitingApproval'), life: 15000 });
        push(getLoginPath());
        return;
      }

      setUserAndPermissions(data, data.permissions ?? []);
      push({ name: 'home' });
    })
    /*
     * A wrong code is not an error the person needs a red banner about — it is the most
     * ordinary outcome of typing six digits, so it belongs under the field. Anything else
     * here is the API being unreachable, and that does get a toast.
     *
     * The API answers with an i18n KEY, not a sentence: it does not know what language the
     * person is reading in, and this screen does.
     */
    .catch((error) => {
      const CHAVE = error?.response?.data?.errors?.code?.[0] ?? error?.response?.data?.errors?.email?.[0];

      if (error?.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY) {
        serverError.value = CHAVE ? t(CHAVE) : t('auth.flow.signUp.verify.invalidCode');
        return;
      }

      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      });
    })
    .finally(() => (isLoading.value = false));
}

function onResend() {
  if (isResendCoolingDown.value) return;

  isResending.value = true;

  RequestEmailOtpService({ email: email.value })
    /*
     * Always the same message. The API refuses to say whether an address has a pending
     * registration, and a screen that said "we could not find that address" would give
     * away exactly what the API is protecting.
     */
    .then(() => {
      toast.add({
        severity: 'success',
        summary: t('auth.flow.signUp.verify.resent'),
        life: 6000,
      });

      onCooldownRestart();
    })
    .catch(onResendError)
    .finally(() => (isResending.value = false));
}

/**
 * A 429 is the API's throttle, not a failure: it says to wait, in words, as a warning. Only what
 * is not a 429 is the red "something went wrong".
 */
function onResendError(error: LoginCodeApiError) {
  const IS_THROTTLED = error?.response?.status === HTTP_STATUS.TOO_MANY_REQUESTS;

  if (IS_THROTTLED) {
    toast.add({ severity: 'warn', summary: t('errors.tooManyAttempts'), life: 10000 });
    return;
  }

  toast.add({
    severity: 'error',
    summary: t('errors.somethingWentWrong'),
    life: 10000,
  });
}
</script>

<template>
  <AuthFlowLayout :title="$t('auth.flow.signUp.verify.title')">
    <Form
      v-slot="$form"
      class="flex w-full flex-col gap-4"
      :initialValues="INITIAL_VALUES"
      :resolver
      @submit="onSubmit"
    >
      <p class="text-muted text-center text-sm wrap-anywhere">
        {{ $t('auth.flow.signUp.verify.subtitle', { email }) }}
      </p>

      <FormField name="code">
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="code"
          >
            {{ $t('auth.flow.signUp.verify.code') }}*
          </label>

          <InputText
            fluid
            id="code"
            autocomplete="one-time-code"
            inputmode="numeric"
            name="code"
            :invalid="$form.code?.invalid"
            :maxlength="6"
            :placeholder="$t('auth.flow.signUp.verify.codePlaceholder')"
          />

          <Message
            v-if="$form.code?.invalid"
            v-bind="$form.code"
            class="w-full pl-3 text-sm"
            severity="error"
            size="small"
            variant="simple"
          >
            {{ $form.code?.error?.message }}
          </Message>

          <Message
            v-else-if="serverError"
            class="w-full pl-3 text-sm"
            severity="error"
            size="small"
            variant="simple"
          >
            {{ serverError }}
          </Message>
        </div>
      </FormField>

      <Button
        class="mt-2"
        type="submit"
        :label="$t('auth.flow.signUp.verify.submit')"
        :loading="isLoading"
      />

      <div class="flex flex-wrap items-center justify-center gap-3">
        <Button
          text
          data-testid="verify-email-resend"
          severity="secondary"
          :disabled="isResendCoolingDown"
          :label="$t('auth.flow.signUp.verify.resend')"
          :loading="isResending"
          @click="onResend"
        />

        <span
          v-if="isResendCoolingDown"
          class="text-muted text-sm"
          data-testid="verify-email-resend-countdown"
        >
          {{ $t('auth.flow.signUp.verify.resendIn', { seconds: resendCooldownSeconds }) }}
        </span>
      </div>
    </Form>
  </AuthFlowLayout>
</template>
