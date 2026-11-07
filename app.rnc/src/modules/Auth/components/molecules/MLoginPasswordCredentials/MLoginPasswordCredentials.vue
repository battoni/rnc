<script setup lang="ts">
import { computed, ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import * as yup from 'yup';
import type { FormSubmitEvent } from '@primevue/forms';
import { intendedPathFrom } from '@Helpers';
import { LoginPasswordCredentialsService, useLoginError } from '@AuthModule';
import { useUserStore } from '@UserModule';

const { setUserAndPermissions } = useUserStore();

const { apiError, onLoginError } = useLoginError();
const { push } = useRouter();
const route = useRoute();
const { t } = useI18n();
const toast = useToast();

const initialValues = {
  identifier: '',
  password: '',
};

const selectButtonNoValidateFormControl = { novalidate: true } as const;

const resolver = yupResolver(
  yup.object().shape({
    identifier: yup
      .string()
      .trim()
      .required(t('auth.flow.login.identifierRequired'))
      .test('phone-or-email', t('auth.flow.login.identifierInvalid'), (value) => {
        if (!value) return false;

        const trimmed = value.trim();

        if (identifierMode.value === 'email') return yup.string().email().isValidSync(trimmed);

        return isValidPhoneDigits(trimmed);
      }),
    password: yup.string().trim().required(t('auth.login.passwordRequired')),
  })
);

const identifierMode = ref<'email' | 'phone'>('phone');
const identifierModeButtonKey = ref(0);
const isLoading = ref(false);

const identifierModeSwitcherOptions = computed(() => [
  {
    icon: 'pi pi-whatsapp',
    label: t('auth.flow.login.identifierAriaWhatsapp'),
    value: 'phone' as const,
  },
  {
    icon: 'pi pi-envelope',
    label: t('auth.flow.login.identifierAriaEmail'),
    value: 'email' as const,
  },
]);

// HELPERS
function phoneDigits(value: string): string {
  return value.replace(/\D/g, '');
}

function isValidPhoneDigits(value: string): boolean {
  const digits = phoneDigits(value);

  return digits.length >= 10 && digits.length <= 11;
}

function identifierToUsername(raw: string): string {
  const trimmed = raw.trim();

  if (trimmed.includes('@')) return trimmed;

  return trimmed.replace(/\D/g, '');
}

// EVENTS
function onIdentifierModeChange(value: 'email' | 'phone' | null | undefined): boolean {
  if (value === 'phone' || value === 'email') {
    if (identifierMode.value === value) return false;

    identifierMode.value = value;
    return true;
  }

  identifierModeButtonKey.value += 1;
  return false;
}

function onIdentifierModeUpdate(
  value: 'email' | 'phone' | null | undefined,
  form: { valid: boolean; setFieldValue?: (field: string, value: unknown) => void } | null | undefined
) {
  if (!onIdentifierModeChange(value)) return;

  form?.setFieldValue?.('identifier', '');
}

function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  isLoading.value = true;
  apiError.value = null;

  const username = identifierToUsername(String(values.identifier ?? ''));
  const password = String(values.password ?? '').trim();

  LoginPasswordCredentialsService({
    identifierMode: identifierMode.value,
    password,
    username,
  })
    .then(({ data }) => {
      setUserAndPermissions(data, data.permissions ?? []);
      toast.add({
        severity: 'success',
        summary: t('auth.login.welcome', { name: data.name }),
        life: 5000,
      });
      /*
       * Where they were trying to go, if the guard parked a destination on the way out.
       *
       * Landing everyone on Home has signed them in and practically lost them: they followed a
       * link to one screen and have to find it again from a list. `intendedPathFrom` refuses
       * anything that is not a plain `/path`, so a crafted `?redirect=https://evil.test` is
       * dropped rather than followed.
       */
      const INTENDED = intendedPathFrom(route.query);

      push(INTENDED ?? { name: 'home' });
    })
    .catch(onLoginError)
    .finally(() => (isLoading.value = false));
}
</script>

<template>
  <Form
    v-slot="$form"
    class="flex w-full flex-col gap-4"
    :initialValues
    :resolver
    :validateOnBlur="false"
    :validateOnValueUpdate="false"
    @submit="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <div class="flex w-full items-center justify-between gap-1">
        <label
          class="text-muted mb-2 block text-sm"
          for="loginIdentifier"
        >
          {{ $t('auth.flow.login.identifierLabel') }}
        </label>

        <SelectButton
          class="identifier-mode-select"
          :key="identifierModeButtonKey"
          name="loginIdentifierMode"
          optionLabel="label"
          optionValue="value"
          size="small"
          :allowEmpty="false"
          :formControl="selectButtonNoValidateFormControl"
          :modelValue="identifierMode"
          :options="identifierModeSwitcherOptions"
          @update:modelValue="onIdentifierModeUpdate($event, $form)"
        >
          <template #option="{ option }">
            <i :class="option.icon" />
          </template>
        </SelectButton>
      </div>

      <FormField name="identifier">
        <div class="flex w-full items-center gap-1">
          <InputMask
            v-if="identifierMode === 'phone'"
            fluid
            id="loginIdentifier"
            autocomplete="username"
            class="min-w-0 flex-1 placeholder:text-sm"
            key="login-identifier-phone"
            mask="(99) 99999-9999"
            name="identifier"
            :invalid="$form.identifier?.invalid"
            :placeholder="$t('auth.flow.login.whatsappPlaceholder')"
          />

          <InputText
            v-else
            fluid
            id="loginIdentifier"
            autocomplete="username"
            class="min-w-0 flex-1 placeholder:text-sm"
            key="login-identifier-email"
            name="identifier"
            type="email"
            :invalid="$form.identifier?.invalid"
            :placeholder="$t('auth.flow.login.emailPlaceholder')"
          />
        </div>

        <Message
          v-if="$form.identifier?.invalid"
          v-bind="$form.identifier"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.identifier?.error?.message }}
        </Message>
      </FormField>
    </div>

    <FormField name="password">
      <label
        class="text-muted mb-2 block text-sm"
        for="loginPassword"
      >
        {{ $t('auth.login.password') }}
      </label>

      <Password
        fluid
        toggleMask
        class="text-sm placeholder:text-sm"
        inputId="loginPassword"
        name="password"
        :feedback="false"
        :inputProps="{ autocomplete: 'current-password' }"
        :invalid="$form.password?.invalid"
        :placeholder="$t('auth.login.passwordPlaceholder')"
        :promptLabel="$t('common.password.promptLabel')"
      />

      <Message
        v-if="$form.password?.invalid"
        v-bind="$form.password"
        class="w-full pl-3 text-sm"
        severity="error"
        size="small"
        variant="simple"
      >
        {{ $form.password?.error?.message }}
      </Message>
    </FormField>

    <AFormError :error="apiError" />

    <FormField>
      <Button
        class="w-full"
        type="submit"
        :label="$t('auth.flow.continue')"
        :loading="isLoading"
      />
    </FormField>

    <Divider
      align="center"
      class="text-muted before:border-line-strong"
    >
      <span>{{ $t('auth.flow.or') }}</span>
    </Divider>

    <div class="flex w-full flex-col space-y-5">
      <Button
        outlined
        class="celer-button-outlined-secondary w-full"
        icon="pi pi-google"
        type="button"
        :label="`${$t('auth.flow.continueWith')} ${$t('auth.flow.social.google')}`"
      />

      <Button
        outlined
        class="celer-button-outlined-secondary w-full"
        icon="pi pi-facebook"
        type="button"
        :label="`${$t('auth.flow.continueWith')} ${$t('auth.flow.social.facebook')}`"
      />

      <RouterLink :to="{ name: 'auth.entrarEmail' }">
        <Button
          outlined
          class="celer-button-outlined-secondary w-full"
          icon="pi pi-envelope"
          type="button"
          :label="`${$t('auth.flow.continueWith')} ${$t('auth.flow.social.email')}`"
        />
      </RouterLink>
    </div>
  </Form>
</template>

<style scoped>
.identifier-mode-select :deep(button[aria-pressed='true']) {
  background-color: var(--p-button-primary-background);
  border-color: var(--p-button-primary-border-color);
}

.identifier-mode-select :deep(button[aria-pressed='true'] .p-togglebutton-content) {
  background-color: var(--p-button-primary-background);
  color: var(--p-button-primary-color);
}

.identifier-mode-select :deep(button[aria-pressed='true'] .p-togglebutton-content i) {
  color: inherit;
}
</style>
