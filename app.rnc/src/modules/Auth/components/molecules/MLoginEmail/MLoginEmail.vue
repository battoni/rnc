<script setup lang="ts">
import { ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import * as yup from 'yup';
import type { FormSubmitEvent } from '@primevue/forms';
import { RequestEmailOtpService } from '@AuthModule';

const { push } = useRouter();
const { t } = useI18n();
const toast = useToast();

const initialValues = {
  email: '',
};

const resolver = yupResolver(
  yup.object().shape({
    email: yup.string().trim().email(t('auth.flow.login.emailInvalid')).required(t('auth.flow.login.emailRequired')),
  })
);

const isLoading = ref(false);

// EVENTS
function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  isLoading.value = true;

  const email = String(values.email ?? '').trim();

  RequestEmailOtpService({ email })
    .then(() => push({ name: 'auth.entrarCodigo', query: { email } }))
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
    <FormField name="email">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="email"
        >
          {{ $t('auth.flow.login.email') }}
        </label>

        <div class="relative">
          <i
            :class="[
              'pi pi-envelope pointer-events-none absolute top-1/2 left-3 -translate-y-1/2',
              $form.email?.invalid ? 'text-danger-500' : 'text-subtle',
            ]"
          />

          <InputText
            fluid
            id="email"
            class="pl-10"
            data-testid="login-email"
            name="email"
            :invalid="$form.email?.invalid"
            :placeholder="$t('auth.flow.login.emailPlaceholder')"
          />
        </div>

        <Message
          v-if="$form.email?.invalid"
          v-bind="$form.email"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.email?.error?.message }}
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
  </Form>
</template>
