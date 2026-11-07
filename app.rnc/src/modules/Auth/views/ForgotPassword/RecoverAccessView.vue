<script setup lang="ts">
import { computed, ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import * as yup from 'yup';
import type { FormSubmitEvent } from '@primevue/forms';
import { RequestPasswordResetService, type RequestPasswordResetPayload } from '@AuthModule';

const { t } = useI18n();
const { push } = useRouter();
const toast = useToast();

const initialValues = {
  email: '',
  phone: '',
};

const resolver = yupResolver(
  yup
    .object({
      phone: yup
        .string()
        .default('')
        .test('phone-rules', '', function phoneRules(value) {
          const digits = phoneDigits(value ?? '');

          if (digits.length === 0) return true;

          const isPhoneLengthInvalid = digits.length < 10 || digits.length > 11;

          if (isPhoneLengthInvalid) {
            return this.createError({ message: t('auth.flow.recover.phoneInvalid') });
          }

          return true;
        }),
      email: yup
        .string()
        .default('')
        .test('email-rules', '', function emailRules(value) {
          const email = String(value ?? '').trim();

          if (!email) return true;

          if (!yup.string().email().isValidSync(email)) {
            return this.createError({ message: t('auth.flow.login.emailInvalid') });
          }

          return true;
        }),
    })
    .test('at-least-one-recover', '', function atLeastOneRecover(value) {
      if (!value) return true;

      const digits = phoneDigits(String(value.phone ?? ''));
      const email = String(value.email ?? '').trim();

      if (digits.length > 0 || email.length > 0) return true;

      return this.createError({
        message: t('auth.flow.recover.fillAtLeastOneField'),
        path: 'phone',
      });
    })
);

const emailValue = ref('');
const isSubmitAttempted = ref(false);
const isSuccessState = ref(false);
const isLoading = ref(false);
const phoneValue = ref('');
const resetToken = ref('');
const isContinueDisabled = computed(() => isLoading.value);
const isRecoverFieldsRequiredInvalid = computed(
  () => isSubmitAttempted.value && phoneDigits(phoneValue.value).length === 0 && emailValue.value.trim().length === 0
);

// HELPERS
function phoneDigits(value: string): string {
  return value.replace(/\D/g, '');
}

function navigateToResetPasswordCodeView(token: string) {
  return push({
    path: '/reset-password',
    query: { token },
  });
}

// EVENTS
function onPhoneUpdate(value: string | undefined) {
  phoneValue.value = String(value ?? '');
}

function onSubmit({ valid, values }: FormSubmitEvent) {
  isSubmitAttempted.value = true;

  if (!valid) return;

  const digits = phoneDigits(String(values.phone ?? ''));
  const email = String(values.email ?? '').trim();

  const payload: RequestPasswordResetPayload = {};

  if (digits.length > 0) payload.phone = digits;

  if (email.length > 0) payload.email = email;

  isLoading.value = true;

  RequestPasswordResetService(payload)
    .then((response) => {
      resetToken.value = response.data.token;
      isSuccessState.value = true;
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

function onConfirmRecoverAccess() {
  const token = resetToken.value || 'mock-reset-token';

  navigateToResetPasswordCodeView(token);
}
</script>

<template>
  <AuthFlowLayout :title="isSuccessState ? $t('auth.flow.recover.successTitle') : $t('auth.flow.recover.title')">
    <template
      v-if="!isSuccessState"
      #topActions
    >
      <RouterLink :to="{ name: 'auth.entrar' }">
        <Button
          link
          class="celer-button-link-secondary"
          icon="pi pi-arrow-left"
          :label="$t('auth.flow.back')"
        />
      </RouterLink>
    </template>

    <Form
      v-if="!isSuccessState"
      v-slot="$form"
      class="flex w-full flex-col gap-4"
      :initialValues
      :resolver
      @submit="onSubmit"
    >
      <p class="text-muted text-sm">
        {{ $t('auth.flow.recover.description') }}
      </p>

      <FormField name="phone">
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="recoverPhone"
          >
            {{ $t('auth.flow.recover.phone') }}
          </label>

          <div class="relative">
            <i
              :class="[
                'pi pi-whatsapp pointer-events-none absolute top-1/2 left-3 -translate-y-1/2',
                isRecoverFieldsRequiredInvalid || (isSubmitAttempted && $form.phone?.invalid)
                  ? 'text-danger-500'
                  : 'text-subtle',
              ]"
            />

            <InputMask
              fluid
              id="recoverPhone"
              class="pl-10"
              mask="(99) 99999-9999"
              name="phone"
              :invalid="isRecoverFieldsRequiredInvalid || (isSubmitAttempted && $form.phone?.invalid)"
              :placeholder="$t('auth.flow.login.whatsappPlaceholder')"
              @update:modelValue="onPhoneUpdate"
            />
          </div>

          <Message
            v-if="isRecoverFieldsRequiredInvalid || (isSubmitAttempted && $form.phone?.invalid)"
            class="w-full pl-3 text-sm"
            severity="error"
            size="small"
            variant="simple"
          >
            {{
              isRecoverFieldsRequiredInvalid ? $t('auth.flow.recover.fillAtLeastOneField') : $form.phone?.error?.message
            }}
          </Message>
        </div>
      </FormField>

      <Divider
        align="center"
        class="text-muted before:border-line-strong my-0 text-sm"
        layout="horizontal"
      >
        <span>{{ $t('auth.flow.or') }}</span>
      </Divider>

      <FormField name="email">
        <div class="flex flex-col gap-2">
          <label
            class="text-muted mb-2 block text-sm"
            for="recoverEmail"
          >
            {{ $t('auth.flow.login.email') }}
          </label>

          <div class="relative">
            <i
              :class="[
                'pi pi-envelope pointer-events-none absolute top-1/2 left-3 -translate-y-1/2',
                isRecoverFieldsRequiredInvalid || (isSubmitAttempted && $form.email?.invalid)
                  ? 'text-danger-500'
                  : 'text-subtle',
              ]"
            />

            <InputText
              v-model="emailValue"
              fluid
              id="recoverEmail"
              class="pl-10"
              name="email"
              type="email"
              :invalid="isRecoverFieldsRequiredInvalid || (isSubmitAttempted && $form.email?.invalid)"
              :placeholder="$t('auth.flow.login.emailPlaceholder')"
            />
          </div>

          <Message
            v-if="isRecoverFieldsRequiredInvalid || (isSubmitAttempted && $form.email?.invalid)"
            class="w-full pl-3 text-sm"
            severity="error"
            size="small"
            variant="simple"
          >
            {{
              isRecoverFieldsRequiredInvalid ? $t('auth.flow.recover.fillAtLeastOneField') : $form.email?.error?.message
            }}
          </Message>
        </div>
      </FormField>

      <FormField>
        <Button
          class="w-full"
          type="submit"
          :disabled="isContinueDisabled"
          :label="$t('auth.flow.continue')"
          :loading="isLoading"
        />
      </FormField>
    </Form>

    <div
      v-else
      class="flex w-full flex-col gap-4"
    >
      <p class="text-muted text-sm">
        {{ $t('auth.flow.recover.successMessage') }}
      </p>

      <Button
        class="w-full"
        type="button"
        :label="$t('auth.flow.recover.confirmLink')"
        @click="onConfirmRecoverAccess"
      />
    </div>
  </AuthFlowLayout>
</template>
