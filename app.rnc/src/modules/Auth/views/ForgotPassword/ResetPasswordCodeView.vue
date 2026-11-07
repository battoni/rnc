<script setup lang="ts">
import { onBeforeMount, ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import * as yup from 'yup';
import type { FormSubmitEvent } from '@primevue/forms';
import { ConfirmPasswordResetService, ResendPasswordResetCodeService } from '@AuthModule';

const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const toast = useToast();

const token = ref('');

const initialValues = {
  code: '',
  password: '',
  passwordConfirmation: '',
};

const resolver = yupResolver(
  yup.object().shape({
    code: yup.string().trim().required(t('auth.flow.resetCode.codeRequired')),
    password: yup
      .string()
      .trim()
      .required(t('auth.login.passwordRequired'))
      .min(8, t('auth.flow.setPassword.minLength')),
    passwordConfirmation: yup
      .string()
      .trim()
      .required(t('auth.flow.setPassword.confirmRequired'))
      .test('match', t('auth.flow.setPassword.mismatch'), function matchPasswordConfirmation(value) {
        return value === this.parent.password;
      }),
  })
);

const code = ref('');
const isLoading = ref(false);
const isResending = ref(false);

onBeforeMount(onRouteCheck);

// HELPERS
function onRouteCheck() {
  const queryToken = route.query.token;

  if (typeof queryToken !== 'string' || !queryToken) {
    push({ name: 'auth.recuperarSenha' });
    return;
  }

  token.value = queryToken;
}

// EVENTS
function onCodeChange(value: string, onFormChange?: (event: { value: string }) => void) {
  code.value = value;
  onFormChange?.({ value });
}

function onResendCode() {
  if (!token.value) return;

  isResending.value = true;

  ResendPasswordResetCodeService({ token: token.value })
    .then(() => {
      toast.add({
        severity: 'success',
        summary: t('auth.flow.resetCode.resendSuccess'),
        life: 5000,
      });
    })
    .catch(() => {
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      });
    })
    .finally(() => (isResending.value = false));
}

function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  if (!token.value) return;

  isLoading.value = true;

  ConfirmPasswordResetService({
    // The ref, not `values.code`: the Form can hold only the last OTP digit (primevue#7025).
    code: code.value.trim(),
    password: String(values.password ?? '').trim(),
    passwordConfirmation: String(values.passwordConfirmation ?? '').trim(),
    token: token.value,
  })
    .then(() => {
      toast.add({
        severity: 'success',
        summary: t('auth.flow.resetCode.success'),
        life: 5000,
      });
      push({ name: 'auth.entrar' });
    })
    .catch(() => {
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        detail: t('errors.refreshAndContactAdmin'),
        life: 10000,
      });
    })
    .finally(() => (isLoading.value = false));
}
</script>

<template>
  <AuthFlowLayout :title="$t('auth.flow.resetCode.title')">
    <Form
      v-slot="$form"
      class="flex w-full flex-col gap-4"
      :initialValues
      :resolver
      @submit="onSubmit"
    >
      <FormField name="password">
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="resetNewPassword"
          >
            {{ $t('auth.flow.resetCode.newPassword') }} *
          </label>

          <Password
            fluid
            toggleMask
            inputId="resetNewPassword"
            name="password"
            :feedback="false"
            :inputProps="{ autocomplete: 'new-password' }"
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
        </div>
      </FormField>

      <FormField name="passwordConfirmation">
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="resetConfirmPassword"
          >
            {{ $t('auth.flow.setPassword.confirmLabel') }} *
          </label>

          <Password
            fluid
            toggleMask
            inputId="resetConfirmPassword"
            name="passwordConfirmation"
            :feedback="false"
            :inputProps="{ autocomplete: 'new-password' }"
            :invalid="$form.passwordConfirmation?.invalid"
            :placeholder="$t('auth.flow.setPassword.confirmPlaceholder')"
            :promptLabel="$t('common.password.promptLabel')"
          />

          <Message
            v-if="$form.passwordConfirmation?.invalid"
            v-bind="$form.passwordConfirmation"
            class="w-full pl-3 text-sm"
            severity="error"
            size="small"
            variant="simple"
          >
            {{ $form.passwordConfirmation?.error?.message }}
          </Message>
        </div>
      </FormField>

      <FormField
        v-slot="{ props: codeFieldProps }"
        name="code"
      >
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="resetCodeOtp"
          >
            {{ $t('auth.flow.recover.enterCode') }}:
          </label>

          <InputOtp
            v-model="code"
            integerOnly
            id="resetCodeOtp"
            class="flex w-full justify-between"
            :invalid="$form.code?.invalid"
            @update:modelValue="(value) => onCodeChange(String(value ?? ''), codeFieldProps.onChange)"
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
        </div>
      </FormField>

      <FormField>
        <Button
          class="w-full"
          type="submit"
          :label="$t('auth.flow.resetCode.confirm')"
          :loading="isLoading"
        />
      </FormField>
    </Form>

    <template #bottomActions>
      <Button
        link
        class="w-full"
        type="button"
        :label="$t('auth.flow.resetCode.resend')"
        :loading="isResending"
        @click="onResendCode"
      />
    </template>
  </AuthFlowLayout>
</template>
