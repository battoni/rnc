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
    username: yup.string().trim().required(t('auth.login.usernameRequired')),
    password: yup.string().trim().required(t('auth.login.passwordRequired')),
  })
);

const isLoading = ref(false);

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
    .finally(() => {
      isLoading.value = false;
    });
}
</script>

<template>
  <p class="text-heading mb-5 text-lg">
    {{ $t('auth.flow.login.welcome') }}
  </p>

  <Form
    v-slot="$form"
    class="flex w-full flex-col gap-4"
    :initialValues
    :resolver
    :validateOnBlur="false"
    :validateOnValueUpdate="false"
    @submit="onSubmit"
  >
    <FormField name="username">
      <label
        class="text-muted mb-2 block text-sm"
        for="loginUsername"
      >
        {{ $t('auth.login.username') }}
      </label>

      <div class="relative isolate">
        <i
          :class="[
            'pi pi-id-card pointer-events-none absolute top-1/2 left-3 z-10 -translate-y-1/2',
            $form.username?.invalid ? 'text-danger-500' : 'text-subtle',
          ]"
        />

        <InputText
          fluid
          id="loginUsername"
          autocomplete="username"
          class="pl-10 placeholder:text-sm"
          data-testid="login-username"
          name="username"
          :invalid="$form.username?.invalid"
          :placeholder="$t('auth.login.usernamePlaceholder')"
        />
      </div>

      <Message
        v-if="$form.username?.invalid"
        v-bind="$form.username"
        class="w-full pl-3 text-sm"
        severity="error"
        size="small"
        variant="simple"
      >
        {{ $form.username?.error?.message }}
      </Message>
    </FormField>

    <FormField name="password">
      <label
        class="text-muted mb-2 block text-sm"
        for="loginPassword"
      >
        {{ $t('auth.login.password') }}
      </label>

      <div class="relative isolate">
        <i
          :class="[
            'pi pi-key pointer-events-none absolute top-1/2 left-3 z-10 -translate-y-1/2',
            $form.password?.invalid ? 'text-danger-500' : 'text-subtle',
          ]"
        />

        <Password
          fluid
          toggleMask
          class="text-sm placeholder:text-sm"
          inputClass="pl-10"
          inputId="loginPassword"
          name="password"
          :feedback="false"
          :inputProps="{ autocomplete: 'current-password' }"
          :invalid="$form.password?.invalid"
          :placeholder="$t('auth.login.passwordPlaceholder')"
          :promptLabel="$t('common.password.promptLabel')"
        />
      </div>

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
        data-testid="login-submit"
        type="submit"
        :label="$t('auth.login.submit')"
        :loading="isLoading"
      />
    </FormField>
  </Form>
</template>
