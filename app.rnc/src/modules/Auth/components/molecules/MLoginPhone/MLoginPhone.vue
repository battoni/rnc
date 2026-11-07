<script setup lang="ts">
import { ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import * as yup from 'yup';
import type { LoginCodeApiError, LoginCodeRequested } from '@AuthModule';
import type { FormSubmitEvent } from '@primevue/forms';
import { HTTP_STATUS } from '@Enums';
import { isValidBrazilPhone } from '@Helpers';
import { LOGIN_CODE_ERROR, RequestPhoneCodeService } from '@AuthModule';

const emit = defineEmits<{
  onSuccess: [response: LoginCodeRequested, typedPhone: string];
}>();
const props = withDefaults(defineProps<{ initialPhone?: string }>(), { initialPhone: '' });

const toast = useToast();
const { t } = useI18n();

const TOAST_LIFE_MS = 10000;

const initialValues = { whatsapp: props.initialPhone };

const resolver = yupResolver(
  yup.object().shape({
    whatsapp: yup
      .string()
      .trim()
      .required(t('auth.flow.login.phoneRequired'))
      .test('brazil-phone', t('auth.flow.login.phoneInvalid'), (value) => isValidBrazilPhone(value ?? '')),
  })
);

const isLoading = ref(false);

// HELPERS
function refusalSummary(error: LoginCodeApiError): string {
  const IS_THROTTLED = error?.response?.status === HTTP_STATUS.TOO_MANY_REQUESTS;
  const IS_UNDELIVERED = error?.response?.data?.code === LOGIN_CODE_ERROR.DELIVERY_FAILED;

  if (IS_THROTTLED) return t('errors.tooManyAttempts');

  return IS_UNDELIVERED ? t('auth.flow.login.codeDeliveryFailed') : t('errors.somethingWentWrong');
}

// EVENTS
function onRequestError(error: LoginCodeApiError) {
  toast.add({
    severity: 'error',
    summary: refusalSummary(error),
    life: TOAST_LIFE_MS,
  });
}

function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  isLoading.value = true;

  const TYPED_PHONE = String(values.whatsapp ?? '');

  RequestPhoneCodeService({ phone: TYPED_PHONE })
    .then(({ data }) => emit('onSuccess', data, TYPED_PHONE))
    .catch(onRequestError)
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
    <h2 class="text-heading text-xl font-semibold">
      {{ $t('auth.flow.login.welcome') }}
    </h2>

    <FormField name="whatsapp">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="whatsapp"
        >
          {{ $t('auth.flow.login.phoneNumber') }}
        </label>

        <div class="relative">
          <i
            :class="[
              'pi pi-whatsapp pointer-events-none absolute top-1/2 left-3 -translate-y-1/2',
              $form.whatsapp?.invalid ? 'text-danger-500' : 'text-subtle',
            ]"
          />

          <InputMask
            fluid
            id="whatsapp"
            autocomplete="tel-national"
            class="pl-10"
            mask="(99) 99999-9999"
            name="whatsapp"
            :invalid="$form.whatsapp?.invalid"
            :placeholder="$t('auth.flow.login.whatsappPlaceholder')"
          />
        </div>

        <Message
          v-if="$form.whatsapp?.invalid"
          v-bind="$form.whatsapp"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.whatsapp?.error?.message }}
        </Message>
      </div>
    </FormField>

    <FormField>
      <Button
        class="w-full"
        type="submit"
        :label="$t('auth.flow.login.sendCode')"
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
        :label="`${$t('auth.flow.continueWith')} ${$t('auth.flow.social.google')}`"
      />

      <Button
        outlined
        class="celer-button-outlined-secondary w-full"
        icon="pi pi-facebook"
        :label="`${$t('auth.flow.continueWith')} ${$t('auth.flow.social.facebook')}`"
      />

      <RouterLink :to="{ name: 'auth.entrarEmail' }">
        <Button
          outlined
          class="celer-button-outlined-secondary w-full"
          icon="pi pi-envelope"
          :label="`${$t('auth.flow.continueWith')} ${$t('auth.flow.social.email')}`"
        />
      </RouterLink>
    </div>
  </Form>
</template>
