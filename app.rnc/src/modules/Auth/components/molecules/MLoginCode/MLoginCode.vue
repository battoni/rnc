<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import * as yup from 'yup';
import { useCountdown } from '@Composables';
import { RequestEmailOtpService, VerifyLoginCodeService } from '@AuthModule';
// Relative, like MLoginPhoneCode: the barrel may still be evaluating when this file is.
import { LOGIN_CODE_RESEND_COOLDOWN_SECONDS } from '../../../constants';

const { remainingSeconds: resendCooldownSeconds, start: startResendCountdown } = useCountdown();
const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const toast = useToast();

const initialValues = {
  code: '',
};

const CODE_LENGTH = 4;

const resolver = yupResolver(
  yup.object().shape({
    // `required` first: an empty string fails `length` too, and the first failing test is the message.
    code: yup
      .string()
      .trim()
      .required(t('auth.flow.login.codeRequired'))
      .length(CODE_LENGTH, t('auth.flow.login.codeInvalid')),
  })
);

const code = ref('');
const isLoading = ref(false);
const isResendTimerVisible = ref(false);

onBeforeMount(startResendCooldown);

// HELPERS
function formatResendCooldown(seconds: number): string {
  const normalizedSeconds = Math.max(seconds, 0);
  const minutes = String(Math.floor(normalizedSeconds / 60)).padStart(2, '0');
  const remainingSeconds = String(normalizedSeconds % 60).padStart(2, '0');

  return `${minutes}:${remainingSeconds}`;
}

/**
 * Measured by the wall clock (`useCountdown`), not by counting ticks: a phone suspends timers while
 * the person reads the code in another app, and the old per-tick countdown came back still locked.
 */
function startResendCooldown() {
  startResendCountdown(LOGIN_CODE_RESEND_COOLDOWN_SECONDS);
}

// EVENTS
function onResendCodeClick() {
  if (resendCooldownSeconds.value > 0) {
    isResendTimerVisible.value = true;

    // A wait is the app refusing on a rule, not something breaking: `warn` (celer-07, Refusals).
    toast.add({
      severity: 'warn',
      summary: t('auth.flow.login.resendCodeWaitTitle'),
      detail: t('auth.flow.login.resendCodeWaitDetail'),
      life: 5000,
    });
    return;
  }

  const phone = String(route.query.phone ?? '').trim();

  if (!phone) {
    toast.add({
      severity: 'error',
      summary: t('errors.somethingWentWrong'),
      detail: t('auth.flow.login.resendCodeMissingPhone'),
      life: 10000,
    });
    return;
  }

  RequestEmailOtpService({ phone })
    .then(() => {
      toast.add({
        severity: 'success',
        summary: t('auth.flow.login.resendCodeSuccess'),
        life: 3000,
      });

      isResendTimerVisible.value = false;
      startResendCooldown();
    })
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      })
    );
}

function onCodeChange(value: string, onFormChange?: (event: { value: string }) => void) {
  code.value = value;
  onFormChange?.({ value });
}

/*
 * The code comes from the local ref, never from the Form: PrimeVue Forms can keep only the last
 * digit of an InputOtp (primevue#7025), so a complete code could be refused as too short — or a
 * single digit sent as the code.
 */
function onSubmit() {
  const CODE = code.value.trim();
  const isComplete = CODE.length === CODE_LENGTH;

  if (!isComplete) return;

  isLoading.value = true;

  VerifyLoginCodeService({ code: CODE })
    .then(() => {
      const email = String(route.query.email ?? '').trim();

      push(email ? { name: 'auth.onboarding', query: { email } } : { name: 'auth.onboarding' });
    })
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      })
    )
    .finally(() => (isLoading.value = false));
}
</script>

<template>
  <Form
    v-slot="$form"
    class="flex w-full flex-col gap-4"
    :initialValues
    :resolver
    @submit="onSubmit"
  >
    <FormField
      v-slot="{ props: codeFieldProps }"
      name="code"
    >
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="loginOtpCode"
        >
          {{ $t('auth.flow.login.enterCode') }}:
        </label>

        <InputOtp
          v-model="code"
          integerOnly
          id="loginOtpCode"
          class="flex w-full justify-between"
          :invalid="$form.code?.invalid"
          :length="CODE_LENGTH"
          @update:modelValue="(value) => onCodeChange(String(value ?? ''), codeFieldProps.onChange)"
        />

        <Message
          v-if="$form.code?.invalid"
          v-bind="$form.code"
          class="w-full text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.code?.error?.message }}
        </Message>
      </div>
    </FormField>

    <FormField>
      <Button
        class="w-full"
        type="submit"
        :label="$t('auth.flow.continue')"
        :loading="isLoading"
      />
    </FormField>

    <div class="flex items-center justify-center gap-2">
      <Button
        text
        class="celer-button-text-secondary text-sm"
        :label="$t('auth.flow.login.resendCode')"
        @click="onResendCodeClick"
      />

      <span
        v-if="isResendTimerVisible && resendCooldownSeconds > 0"
        class="text-muted text-sm"
      >
        {{ formatResendCooldown(resendCooldownSeconds) }}
      </span>
    </div>
  </Form>
</template>
