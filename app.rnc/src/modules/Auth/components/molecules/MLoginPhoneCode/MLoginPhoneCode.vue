<script setup lang="ts">
import { computed, nextTick, onBeforeMount, ref, useTemplateRef, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { useI18n } from 'vue-i18n';
import type { LoginCodeApiError, LoginCodeRequested } from '@AuthModule';
import type { User } from '@UserModule';
import { useCountdown } from '@Composables';
import { HTTP_STATUS } from '@Enums';
import { formatBrazilPhone, translateError } from '@Helpers';
import { RequestPhoneCodeService, VerifyPhoneCodeService } from '@AuthModule';
// Relative, not `@AuthModule`: the prop default below is read while the barrel is still being
// evaluated, and through the barrel it is not initialised yet — the whole app went blank.
import { LOGIN_CODE_LENGTH, LOGIN_CODE_RESEND_COOLDOWN_SECONDS } from '../../../constants';
import { LOGIN_CODE_ERROR } from '../../../enums';

const emit = defineEmits<{
  onChangePhone: [];
  onCodeExpired: [];
  onCodeLocked: [];
  onResent: [response: LoginCodeRequested];
  onSuccess: [user: User];
}>();
const props = withDefaults(
  defineProps<{
    devCode?: string;
    phone: string;
    resendCooldownSeconds?: number;
  }>(),
  {
    devCode: undefined,
    resendCooldownSeconds: LOGIN_CODE_RESEND_COOLDOWN_SECONDS,
  }
);

const { remainingSeconds: cooldownSeconds, start: startCooldownCountdown } = useCountdown();
const { t } = useI18n();

/** `one-time-code` lets the phone offer the code straight from the message it arrived in. */
const OTP_PT = { pcInputText: { root: { autocomplete: 'one-time-code' } } };

const otpInput = useTemplateRef<ComponentPublicInstance>('otpInput');

const code = ref('');
const errorMessage = ref<string | null>(null);
const isResending = ref(false);
const isVerifying = ref(false);

const canResend = computed(() => cooldownSeconds.value === 0 && !isResending.value);
const displayPhone = computed(() => formatBrazilPhone(props.phone));
const isCodeComplete = computed(() => code.value.length === LOGIN_CODE_LENGTH);

watch(code, onCodeUpdate);

onBeforeMount(startCooldown);

// HELPERS
function focusFirstDigit() {
  const FIRST_INPUT = otpInput.value?.$el?.querySelector?.('input') as HTMLInputElement | null | undefined;

  FIRST_INPUT?.focus();
}

/**
 * Measured by the wall clock (`useCountdown`): a phone suspends timers while the person reads the
 * code in another app, and a countdown that only decremented per tick kept "resend" locked long
 * after the minute it promised.
 */
function startCooldown() {
  startCooldownCountdown(props.resendCooldownSeconds);
}

// EVENTS
function onChangePhoneClick() {
  emit('onChangePhone');
}

function onCodeUpdate(value: string) {
  // Only a keystroke clears the error — the reset after a wrong code must leave it on screen.
  if (value.length > 0) errorMessage.value = null;

  const SHOULD_SUBMIT = value.length === LOGIN_CODE_LENGTH && !isVerifying.value;

  if (SHOULD_SUBMIT) onVerify();
}

function onResendClick() {
  if (!canResend.value) return;

  isResending.value = true;
  errorMessage.value = null;

  RequestPhoneCodeService({ phone: props.phone })
    .then(({ data }) => {
      emit('onResent', data);

      startCooldown();
    })
    .catch(onResendError)
    .finally(() => (isResending.value = false));
}

function onResendError(error: LoginCodeApiError) {
  const IS_THROTTLED = error?.response?.status === HTTP_STATUS.TOO_MANY_REQUESTS;
  const IS_UNDELIVERED = error?.response?.data?.code === LOGIN_CODE_ERROR.DELIVERY_FAILED;

  if (IS_THROTTLED) {
    errorMessage.value = t('errors.tooManyAttempts');
    return;
  }

  errorMessage.value = IS_UNDELIVERED ? t('auth.flow.login.codeDeliveryFailed') : t('errors.somethingWentWrong');
}

function onVerify() {
  if (!isCodeComplete.value) return;

  isVerifying.value = true;
  errorMessage.value = null;

  VerifyPhoneCodeService({ code: code.value, phone: props.phone })
    .then(({ data }) => emit('onSuccess', data))
    .catch(onVerifyError)
    .finally(onVerifySettled);
}

function onVerifyError(error: LoginCodeApiError) {
  const STATUS = error?.response?.status;
  const DATA = error?.response?.data;

  if (DATA?.code === LOGIN_CODE_ERROR.EXPIRED) {
    emit('onCodeExpired');
    return;
  }

  // The fifth wrong guess burned the code; typing more here can never work.
  if (DATA?.code === LOGIN_CODE_ERROR.LOCKED) {
    emit('onCodeLocked');
    return;
  }

  if (DATA?.code === LOGIN_CODE_ERROR.INVALID) {
    code.value = '';
    errorMessage.value = t('auth.flow.login.codeInvalid');
    return;
  }

  // A deactivated account, named by the API only after the right code — its key, translated.
  const REFUSAL_KEY = DATA?.errors && Object.values(DATA.errors)[0]?.[0];

  if (STATUS === HTTP_STATUS.UNPROCESSABLE_ENTITY && REFUSAL_KEY) {
    errorMessage.value = translateError(REFUSAL_KEY);
    return;
  }

  errorMessage.value =
    STATUS === HTTP_STATUS.TOO_MANY_REQUESTS ? t('errors.tooManyAttempts') : t('errors.somethingWentWrong');
}

/** The boxes are disabled while checking — focus returns only once they are enabled again. */
function onVerifySettled() {
  isVerifying.value = false;

  if (errorMessage.value) nextTick(focusFirstDigit);
}
</script>

<template>
  <div
    class="flex w-full flex-col gap-4"
    data-testid="login-phone-code"
  >
    <p class="text-muted">
      {{ $t('auth.flow.login.codeSentTo') }}

      <strong
        class="text-heading whitespace-nowrap"
        data-testid="login-phone-code-phone"
      >
        {{ displayPhone }}
      </strong>
    </p>

    <Message
      v-if="devCode"
      class="w-full"
      data-testid="login-phone-code-dev-code"
      severity="info"
      size="small"
    >
      {{ $t('auth.flow.login.devCode') }}

      <strong>{{ devCode }}</strong>
    </Message>

    <div class="flex flex-col gap-2">
      <label
        for="loginPhoneCode"
        :class="['mb-2 block text-sm', errorMessage ? 'text-danger-700' : 'text-muted']"
      >
        {{ $t('auth.flow.login.enterCode') }}
      </label>

      <InputOtp
        v-model="code"
        integerOnly
        id="loginPhoneCode"
        class="flex w-full justify-between"
        ref="otpInput"
        :disabled="isVerifying"
        :invalid="!!errorMessage"
        :length="LOGIN_CODE_LENGTH"
        :pt="OTP_PT"
      />

      <Message
        v-if="errorMessage"
        class="w-full text-sm"
        data-testid="login-phone-code-error"
        severity="error"
        size="small"
        variant="simple"
      >
        {{ errorMessage }}
      </Message>
    </div>

    <Button
      class="w-full"
      type="button"
      :disabled="!isCodeComplete"
      :label="$t('auth.flow.continue')"
      :loading="isVerifying"
      @click="onVerify"
    />

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <Button
          text
          data-testid="login-phone-code-resend"
          severity="secondary"
          size="small"
          :disabled="!canResend"
          :label="$t('auth.flow.login.resendCode')"
          :loading="isResending"
          @click="onResendClick"
        />

        <span
          v-if="cooldownSeconds > 0"
          class="text-muted text-sm"
          data-testid="login-phone-code-countdown"
        >
          {{ $t('auth.flow.login.resendIn', { seconds: cooldownSeconds }) }}
        </span>
      </div>

      <Button
        text
        icon="pi pi-pencil"
        severity="secondary"
        size="small"
        :label="$t('auth.flow.login.changePhone')"
        @click="onChangePhoneClick"
      />
    </div>
  </div>
</template>
