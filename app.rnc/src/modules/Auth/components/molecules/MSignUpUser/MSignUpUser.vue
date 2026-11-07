<script setup lang="ts">
import { ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import * as yup from 'yup';
import type { RegisterUserResponse } from '@AuthModule';
import type { FormSubmitEvent } from '@primevue/forms';
import { BRAZIL_PHONE_MASK } from '@Constants';
import { HTTP_STATUS } from '@Enums';
import { fromDayMonthYear, isValidBrazilPhone, toLocalDateString } from '@Helpers';
import { RegisterUserService } from '@AuthModule';

const { push } = useRouter();
const { query } = useRoute();
const { t, te } = useI18n();
const toast = useToast();

const initialValues = {
  birthDate: '',
  cpf: '',
  email: String(query.email ?? '').trim(),
  firstName: '',
  lastName: '',
  password: '',
  passwordConfirmation: '',
  phone: '',
  termsAccepted: false,
};

const resolver = yupResolver(
  yup.object().shape({
    birthDate: yup.string().trim().required(t('auth.flow.signUp.birthDateRequired')),
    cpf: yup.string().trim().required(t('auth.flow.signUp.cpfRequired')),
    email: yup.string().trim().required(t('auth.flow.signUp.emailRequired')).email(t('auth.flow.signUp.emailInvalid')),
    firstName: yup.string().trim().required(t('auth.flow.signUp.nameRequired')),
    lastName: yup.string().trim().required(t('auth.flow.signUp.lastNameRequired')),
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
    // Optional, but when given it is a number someone can be reached on: area code plus 8 or 9 digits.
    phone: yup
      .string()
      .trim()
      .test('phone', t('auth.flow.signUp.phoneInvalid'), (value) => !value || isValidBrazilPhone(value)),
    termsAccepted: yup.boolean().oneOf([true], t('auth.flow.signUp.termsRequired')),
  })
);

const isLoading = ref(false);
/** Field messages from the API — Yup never knows about “already taken”. */
const serverErrors = ref<Record<string, string>>({});

// HELPERS

/**
 * The field wears a `dd/mm/yyyy` mask; the API is given a date.
 *
 * The mask is this screen's decision and nobody else's, so the screen is what undoes it.
 * Sending the masked string means the API reads a month of 17 and refuses the whole
 * registration — which is exactly how this was found.
 *
 * Through the shared local-date helpers, never `toISOString()`: a day is a local fact, and UTC
 * is already tomorrow in São Paulo after 21:00. A day that does not exist (31/02) is sent as
 * typed, so the API refuses it and the field says so.
 */
function toIsoDate(masked: string): string {
  const DATE = fromDayMonthYear(masked);

  return DATE ? toLocalDateString(DATE) : masked;
}

function isAlreadyTakenMessage(message: string): boolean {
  return /already been taken|já (está|foi) (em uso|utilizado)/i.test(message);
}

/** Laravel uses snake_case for some keys; the form fields are camelCase. */
function toFormFieldName(apiField: string): string {
  if (apiField === 'password_confirmation') return 'passwordConfirmation';

  return apiField;
}

/**
 * `RegisterRequest` declares no `messages()`, so a 422 arrives as Laravel's default English —
 * "The email has already been taken.", not a key. Handing that to `translateError` drops it
 * inside the `errors.unexpected` template, and the field ends up reading "An unexpected error
 * occurred. Copy the error below and inform the system administrator: …" where one short
 * correction belongs. That is the normal case here, not an edge one.
 *
 * So the shapes this form can actually provoke are named, and anything else says, in the
 * reader's language, that the field needs another look. The English sentence is not thrown at
 * them — `errorHandler` already put the whole envelope in the console for whoever is debugging.
 */
function messageForField(field: string, apiMessage: string): string {
  // Auth flows elsewhere in this API do answer with keys; honour one when that is what arrived.
  if (te(apiMessage)) return t(apiMessage);

  if (isAlreadyTakenMessage(apiMessage)) {
    if (field === 'email') return t('auth.flow.signUp.emailTaken');
    if (field === 'cpf') return t('auth.flow.signUp.cpfTaken');
  }

  // Yup requires a birth date but cannot know "before today"; the API is the first to say so.
  if (field === 'birthDate') return t('auth.flow.signUp.birthDateInvalid');

  return t('auth.flow.signUp.fieldInvalid');
}

// EVENTS

/*
 * A server message is about the value that was SENT. The moment the field is edited it describes
 * nothing, and leaving it up tells someone who just typed a brand-new address that it is already
 * taken — with the red border to match — until they submit again. Which is the point at which
 * most people stop submitting.
 */
function onFieldInput(field: string) {
  if (!serverErrors.value[field]) return;

  const REMAINING = { ...serverErrors.value };
  delete REMAINING[field];

  serverErrors.value = REMAINING;
}

function onSubmit({ valid, values }: FormSubmitEvent) {
  if (!valid) return;

  isLoading.value = true;
  serverErrors.value = {};

  const EMAIL = String(values.email ?? '').trim();

  RegisterUserService({
    birthDate: toIsoDate(String(values.birthDate ?? '')),
    cpf: String(values.cpf ?? ''),
    email: EMAIL,
    firstName: String(values.firstName ?? ''),
    lastName: String(values.lastName ?? ''),
    password: String(values.password ?? '').trim(),
    password_confirmation: String(values.passwordConfirmation ?? '').trim(),
    phone: String(values.phone ?? '').trim() || undefined,
    termsAccepted: Boolean(values.termsAccepted),
  })
    .then(({ data }) => onRegistered(EMAIL, data))
    .catch((error) => onRegisterError(error))
    .finally(() => (isLoading.value = false));
}

/**
 * A 422 carries `errors` per field. The Laravel `message` summary concatenates them in English
 * (“and 1 more error”) and is useless on a Portuguese screen — map the bag instead.
 */
function onRegisterError(error: {
  response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } };
  translatedMessage?: string;
}) {
  const STATUS = error?.response?.status;
  const ERRORS = error?.response?.data?.errors;
  const HAS_FIELD_ERRORS =
    STATUS === HTTP_STATUS.UNPROCESSABLE_ENTITY && Boolean(ERRORS && Object.keys(ERRORS).length > 0);

  if (HAS_FIELD_ERRORS && ERRORS) {
    const NEXT: Record<string, string> = {};

    for (const [apiField, messages] of Object.entries(ERRORS)) {
      const FIRST = messages?.[0];
      if (!FIRST) continue;

      const FIELD = toFormFieldName(apiField);
      NEXT[FIELD] = messageForField(FIELD, FIRST);
    }

    serverErrors.value = NEXT;

    const FIRST_MESSAGE = Object.values(NEXT)[0];
    if (FIRST_MESSAGE) {
      toast.add({
        severity: 'error',
        summary: FIRST_MESSAGE,
        life: 10000,
      });
    }
    return;
  }

  toast.add({
    severity: 'error',
    summary: error?.translatedMessage ?? t('errors.somethingWentWrong'),
    detail: t('errors.refreshAndContactAdmin'),
    life: 10000,
  });
}

/**
 * An `id` means the product has e-mail verification switched off: the account exists and
 * the person is already signed in, so there is nowhere to send them but in. Otherwise a
 * code is on its way and the next screen is where they type it.
 */
function onRegistered(email: string, data?: RegisterUserResponse) {
  if (data?.id) {
    push({ name: 'home' });
    return;
  }

  push({ name: 'auth.verifyEmail', query: { email } });
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
    <FormField name="email">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="email"
        >
          {{ $t('auth.flow.signUp.email') }}*
        </label>

        <InputText
          fluid
          id="email"
          autocomplete="email"
          name="email"
          type="email"
          :invalid="$form.email?.invalid || !!serverErrors.email"
          :placeholder="$t('auth.flow.signUp.emailPlaceholder')"
          @input="onFieldInput('email')"
        />

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

        <Message
          v-else-if="serverErrors.email"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ serverErrors.email }}
        </Message>
      </div>
    </FormField>

    <FormField name="firstName">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="firstName"
        >
          {{ $t('auth.flow.signUp.name') }}*
        </label>

        <InputText
          fluid
          id="firstName"
          name="firstName"
          :invalid="$form.firstName?.invalid"
          :placeholder="$t('auth.flow.signUp.namePlaceholder')"
        />

        <Message
          v-if="$form.firstName?.invalid"
          v-bind="$form.firstName"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.firstName?.error?.message }}
        </Message>
      </div>
    </FormField>

    <FormField name="lastName">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="lastName"
        >
          {{ $t('auth.flow.signUp.lastName') }}*
        </label>

        <InputText
          fluid
          id="lastName"
          name="lastName"
          :invalid="$form.lastName?.invalid"
          :placeholder="$t('auth.flow.signUp.lastNamePlaceholder')"
        />

        <Message
          v-if="$form.lastName?.invalid"
          v-bind="$form.lastName"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.lastName?.error?.message }}
        </Message>
      </div>
    </FormField>

    <FormField name="phone">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="phone"
        >
          {{ $t('auth.flow.signUp.phone') }}
        </label>

        <div class="relative">
          <InputMask
            fluid
            id="phone"
            autocomplete="tel"
            class="pl-10"
            name="phone"
            :invalid="$form.phone?.invalid || !!serverErrors.phone"
            :mask="BRAZIL_PHONE_MASK"
            :placeholder="$t('auth.flow.signUp.phonePlaceholder')"
            @input="onFieldInput('phone')"
          />

          <i
            aria-hidden="true"
            class="pi pi-whatsapp text-subtle pointer-events-none absolute top-1/2 left-3 -translate-y-1/2"
          />
        </div>

        <Message
          v-if="$form.phone?.invalid"
          v-bind="$form.phone"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.phone?.error?.message }}
        </Message>

        <Message
          v-else-if="serverErrors.phone"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ serverErrors.phone }}
        </Message>

        <p class="text-muted text-sm">
          {{ $t('auth.flow.signUp.phoneDescription') }}
        </p>
      </div>
    </FormField>

    <FormField name="cpf">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="cpf"
        >
          {{ $t('auth.flow.signUp.cpf') }}*
        </label>

        <InputMask
          fluid
          id="cpf"
          mask="999.999.999-99"
          name="cpf"
          :invalid="$form.cpf?.invalid || !!serverErrors.cpf"
          :placeholder="$t('auth.flow.signUp.cpfPlaceholder')"
          @input="onFieldInput('cpf')"
        />

        <Message
          v-if="$form.cpf?.invalid"
          v-bind="$form.cpf"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.cpf?.error?.message }}
        </Message>

        <Message
          v-else-if="serverErrors.cpf"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ serverErrors.cpf }}
        </Message>

        <p class="text-muted text-sm">
          {{ $t('auth.flow.signUp.cpfDescription') }}
        </p>
      </div>
    </FormField>

    <FormField name="birthDate">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="birthDate"
        >
          {{ $t('auth.flow.signUp.birthDate') }}*
        </label>

        <div class="relative">
          <i
            :class="[
              'pi pi-calendar pointer-events-none absolute top-1/2 left-3 -translate-y-1/2',
              $form.birthDate?.invalid ? 'text-danger-500' : 'text-subtle',
            ]"
          />

          <InputMask
            fluid
            id="birthDate"
            class="pl-10"
            mask="99/99/9999"
            name="birthDate"
            :invalid="$form.birthDate?.invalid"
            :placeholder="$t('auth.flow.signUp.birthDatePlaceholder')"
          />
        </div>

        <Message
          v-if="$form.birthDate?.invalid"
          v-bind="$form.birthDate"
          class="w-full pl-3 text-sm"
          severity="error"
          size="small"
          variant="simple"
        >
          {{ $form.birthDate?.error?.message }}
        </Message>
      </div>
    </FormField>

    <FormField name="password">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="signUpPassword"
        >
          {{ $t('auth.login.password') }}
        </label>

        <Password
          fluid
          toggleMask
          inputId="signUpPassword"
          name="password"
          :feedback="true"
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
          for="signUpPasswordConfirmation"
        >
          {{ $t('auth.flow.setPassword.confirmLabel') }}
        </label>

        <Password
          fluid
          toggleMask
          inputId="signUpPasswordConfirmation"
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

    <FormField name="termsAccepted">
      <div class="flex items-start gap-2">
        <Checkbox
          binary
          inputId="termsAccepted"
          name="termsAccepted"
          :invalid="$form.termsAccepted?.invalid"
        />

        <label
          class="text-muted text-sm"
          for="termsAccepted"
        >
          {{ $t('auth.flow.signUp.iAcceptThe') }}

          <RouterLink
            class="text-primary font-semibold"
            target="_blank"
            to="/termos"
          >
            {{ $t('auth.flow.signUp.termsAndConditionsOfUse') }}.
          </RouterLink>
        </label>
      </div>

      <Message
        v-if="$form.termsAccepted?.invalid"
        v-bind="$form.termsAccepted"
        class="w-full pl-3 text-sm"
        severity="error"
        size="small"
        variant="simple"
      >
        {{ $form.termsAccepted?.error?.message }}
      </Message>
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
