<script setup lang="ts">
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { LoginCodeRequested } from '@AuthModule';
import type { User } from '@UserModule';
import { intendedPathFrom } from '@Helpers';
import { useUserStore } from '@UserModule';

const { setUserAndPermissions } = useUserStore();

const route = useRoute();
const toast = useToast();
const { push } = useRouter();
const { t } = useI18n();

const LOCKED_TOAST_LIFE_MS = 8000;
const WELCOME_TOAST_LIFE_MS = 5000;

// The step machine is this one ref: null is the phone step, anything else the code step.
const codeRequest = ref<LoginCodeRequested | null>(null);
const hasCodeExpired = ref(false);
const lastTypedPhone = ref('');

const title = computed(() => (codeRequest.value ? 'auth.flow.login.codeTitle' : 'auth.flow.login.phoneTitle'));

// EVENTS
function onChangePhone() {
  codeRequest.value = null;
}

function onCodeExpired() {
  codeRequest.value = null;
  hasCodeExpired.value = true;
}

/*
 * Too many wrong guesses burned the code. Back to the phone, still filled in, with the reason — a
 * toast rather than the warning above the form, because this one is an event the person caused
 * and should read once, not a state the form is in.
 */
function onCodeLocked() {
  codeRequest.value = null;
  hasCodeExpired.value = false;

  toast.add({
    severity: 'info',
    summary: t('auth.flow.login.codeLockedTitle'),
    detail: t('auth.flow.login.codeLocked'),
    life: LOCKED_TOAST_LIFE_MS,
  });
}

function onCodeResent(response: LoginCodeRequested) {
  codeRequest.value = response;
}

function onPhoneSuccess(response: LoginCodeRequested, typedPhone: string) {
  lastTypedPhone.value = typedPhone;
  hasCodeExpired.value = false;
  codeRequest.value = response;
}

/*
 * The session is stored HERE, with the same call the password forms make, so the store and the
 * guard behave identically whichever door the person came through.
 */
function onVerifySuccess(user: User) {
  setUserAndPermissions(user, user.permissions ?? []);

  toast.add({
    severity: 'success',
    summary: t('auth.login.welcome', { name: user.name }),
    life: WELCOME_TOAST_LIFE_MS,
  });

  push(intendedPathFrom(route.query) ?? { name: 'home' });
}
</script>

<template>
  <AuthFlowLayout :title="$t(title)">
    <Message
      v-if="hasCodeExpired && !codeRequest"
      class="mb-4 w-full"
      data-testid="login-phone-code-expired"
      severity="warn"
      size="small"
    >
      {{ $t('auth.flow.login.codeExpired') }}
    </Message>

    <!-- A resend keeps the phone, so the step keeps its countdown; a new number remounts it. -->
    <MLoginPhoneCode
      v-if="codeRequest"
      :key="codeRequest.phone"
      :devCode="codeRequest.dev_code"
      :phone="codeRequest.phone"
      @onChangePhone="onChangePhone"
      @onCodeExpired="onCodeExpired"
      @onCodeLocked="onCodeLocked"
      @onResent="onCodeResent"
      @onSuccess="onVerifySuccess"
    />

    <MLoginPhone
      v-else
      :initialPhone="lastTypedPhone"
      @onSuccess="onPhoneSuccess"
    />

    <template #bottomActions>
      <RouterLink :to="{ name: 'auth.support' }">
        <p class="text-muted text-center text-base">{{ $t('auth.flow.needHelp') }}</p>
      </RouterLink>
    </template>
  </AuthFlowLayout>
</template>
