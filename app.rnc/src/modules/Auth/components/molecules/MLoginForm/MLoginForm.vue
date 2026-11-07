<script setup lang="ts">
import { ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import * as yup from 'yup';
import type { FormSubmitEvent } from '@primevue/forms';
import { intendedPathFrom } from '@Helpers';
import { LoginRequestService, useLoginError } from '@AuthModule';
import { useUserStore } from '@UserModule';

const { setUserAndPermissions } = useUserStore();
const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const toast = useToast();
const { apiError, onLoginError } = useLoginError();

const initialValues = {
  username: '',
  password: '',
};

const resolver = yupResolver(
  yup.object().shape({
    username: yup.string().trim().required('auth.login.usernameRequired'),
    password: yup.string().trim().required('auth.login.passwordRequired'),
  })
);

const isLoading = ref(false);

// EVENTS
function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  isLoading.value = true;
  apiError.value = null;

  const username = String(values.username ?? '').trim();
  const password = String(values.password ?? '').trim();

  LoginRequestService({ username, password })
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
    @submit="onSubmit"
  >
    <FormField name="username">
      <IftaLabel>
        <InputText
          fluid
          id="username"
          autocomplete="username"
          name="username"
          :placeholder="$t('auth.login.usernamePlaceholder')"
        />

        <label for="username">{{ $t('auth.login.username') }}</label>
      </IftaLabel>

      <Message
        v-if="$form.username?.invalid"
        severity="error"
        size="small"
        variant="simple"
      >
        {{ $t($form.username.error.message) }}
      </Message>
    </FormField>

    <FormField name="password">
      <IftaLabel>
        <Password
          fluid
          toggleMask
          inputId="password"
          name="password"
          :feedback="false"
          :inputProps="{ autocomplete: 'current-password' }"
          :placeholder="$t('auth.login.passwordPlaceholder')"
          :promptLabel="$t('common.password.promptLabel')"
        />

        <label for="password">{{ $t('auth.login.password') }}</label>
      </IftaLabel>

      <Message
        v-if="$form.password?.invalid"
        severity="error"
        size="small"
        variant="simple"
      >
        {{ $t($form.password.error.message) }}
      </Message>
    </FormField>

    <AFormError :error="apiError" />

    <FormField>
      <Button
        class="w-full"
        type="submit"
        :label="$t('auth.login.submit')"
        :loading="isLoading"
      />
    </FormField>

    <RouterLink to="/esqueci-minha-senha">
      <p class="text-muted text-center text-base">{{ $t('auth.login.forgotPassword') }}</p>
    </RouterLink>
  </Form>
</template>
