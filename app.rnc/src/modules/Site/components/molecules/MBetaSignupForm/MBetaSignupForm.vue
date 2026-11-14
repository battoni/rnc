<script setup lang="ts">
import { computed, onMounted, reactive, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BetaConsent, BetaSignupPayload, BetaSignupResult } from '../../../interfaces';
import { useFirmBrand } from '@Composables';
import { BRAZIL_STATES } from '@Constants';
import { useTracking } from '../../../composables';
import { BETA_MESSAGE_MAX_LENGTH } from '../../../constants';
import { BETA_CLIENTS_BAND } from '../../../enums';
import { GetBetaConsentService, SignUpForBetaService } from '../../../services';

/*
 * The root site's "join the beta" form (rnc-4 R29), on the API of F6a: the firm, who is writing, how
 * to reach them, where the firm is, how many clients it serves, an optional message and the consent,
 * which must be given. The consent's words are the API's (`GET public/beta-signups/consent`, W61), so
 * the words shown are the words stored; without them the form asks nothing and offers to try again.
 * Each field is checked here first and refused on the field; a 422 puts the API's refusals on the
 * fields it names, in this form's own words. A 201 is the success state, which replaces the form.
 * The visit's source rides along as on the diagnostic (`useTracking().visitContext()`).
 */
type FieldName =
  | 'city'
  | 'clients_band'
  | 'company'
  | 'consent'
  | 'email'
  | 'message'
  | 'name'
  | 'phone'
  | 'role'
  | 'uf';
type FieldErrors = Partial<Record<FieldName, string>>;

interface SignupFailure {
  response?: { data?: { errors?: Record<string, string[]> }; status?: number };
}

const emit = defineEmits<{
  onSuccess: [result: BetaSignupResult];
}>();

const { platformName } = useFirmBrand();

const fieldId = useId();
const { t } = useI18n();
const { visitContext } = useTracking();

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const HTTP_TOO_MANY_REQUESTS = 429;
const HTTP_UNPROCESSABLE = 422;
const PHONE_DIGITS = { max: 13, min: 10 };
const REQUIRED_TEXT_FIELDS = ['company', 'name', 'role', 'city'] as const;
const CLIENTS_BANDS = [
  BETA_CLIENTS_BAND.UP_TO_100,
  BETA_CLIENTS_BAND.FROM_101_TO_300,
  BETA_CLIENTS_BAND.FROM_301_TO_1000,
  BETA_CLIENTS_BAND.ABOVE_1000,
];
const TEXT_FIELDS = [
  { autocomplete: 'organization', field: 'company' },
  { autocomplete: 'name', field: 'name' },
  { autocomplete: 'organization-title', field: 'role' },
] as const;
// A field the API names in a 422 reads this form's own sentence for it, never the API's prose.
const REFUSAL_KEYS: Record<FieldName, string> = {
  city: 'site.product.form.errors.invalid',
  clients_band: 'site.product.form.errors.band',
  company: 'site.product.form.errors.invalid',
  consent: 'site.product.form.errors.consent',
  email: 'site.product.form.errors.email',
  message: 'site.product.form.errors.messageTooLong',
  name: 'site.product.form.errors.invalid',
  phone: 'site.product.form.errors.phone',
  role: 'site.product.form.errors.invalid',
  uf: 'site.product.form.errors.uf',
};

const consent = ref<BetaConsent | null>(null);
const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const hasConsentFailed = ref(false);
const isSending = ref(false);
const result = ref<BetaSignupResult | null>(null);

const form = reactive({
  city: '',
  clients_band: null as BETA_CLIENTS_BAND | null,
  company: '',
  consent: false,
  email: '',
  message: '',
  name: '',
  phone: '',
  role: '',
  uf: null as string | null,
});

const isConsentLoading = computed(() => !consent.value && !hasConsentFailed.value);

// A refusal belongs to the value that was refused: once its field changes, it no longer holds.
(Object.keys(form) as FieldName[]).forEach((field) =>
  watch(
    () => form[field],
    () => delete errors.value[field]
  )
);

onMounted(onComponentMount);

// HELPERS
function inputId(field: string): string {
  return `${fieldId}-${field}`;
}

function validate(): FieldErrors {
  const found: FieldErrors = {};
  const PHONE_LENGTH = form.phone.replace(/\D/g, '').length;

  REQUIRED_TEXT_FIELDS.forEach((field) => {
    if (!form[field].trim()) found[field] = t('site.product.form.errors.required');
  });

  if (!EMAIL_PATTERN.test(form.email.trim())) found.email = t('site.product.form.errors.email');

  if (PHONE_LENGTH < PHONE_DIGITS.min || PHONE_LENGTH > PHONE_DIGITS.max) {
    found.phone = t('site.product.form.errors.phone');
  }

  if (!form.uf) found.uf = t('site.product.form.errors.uf');

  if (!form.clients_band) found.clients_band = t('site.product.form.errors.band');

  if (form.message.length > BETA_MESSAGE_MAX_LENGTH) {
    found.message = t('site.product.form.errors.messageTooLong', { max: BETA_MESSAGE_MAX_LENGTH });
  }

  if (!form.consent) found.consent = t('site.product.form.errors.consent');

  return found;
}

function buildPayload(): BetaSignupPayload {
  const { medium, source } = visitContext();

  return {
    city: form.city.trim(),
    clients_band: form.clients_band as BETA_CLIENTS_BAND,
    company: form.company.trim(),
    consent: true,
    email: form.email.trim().toLowerCase(),
    medium,
    message: form.message.trim() || null,
    name: form.name.trim(),
    phone: form.phone.trim(),
    role: form.role.trim(),
    source,
    uf: form.uf as string,
  };
}

function refusalsOf(apiErrors: Record<string, string[]>): FieldErrors {
  const found: FieldErrors = {};

  (Object.keys(apiErrors) as FieldName[])
    .filter((field) => field in REFUSAL_KEYS)
    .forEach((field) => (found[field] = t(REFUSAL_KEYS[field], { max: BETA_MESSAGE_MAX_LENGTH })));

  return found;
}

// EVENTS
function fetchConsent() {
  hasConsentFailed.value = false;

  GetBetaConsentService()
    .then(({ data }) => (consent.value = data))
    .catch(() => (hasConsentFailed.value = true));
}

function onComponentMount() {
  fetchConsent();
}

function onBandPick(band: BETA_CLIENTS_BAND) {
  form.clients_band = band;
}

function onFailed(failure: SignupFailure) {
  const STATUS = failure?.response?.status;
  const API_ERRORS = failure?.response?.data?.errors;

  if (STATUS === HTTP_UNPROCESSABLE && API_ERRORS) {
    errors.value = refusalsOf(API_ERRORS);
    return;
  }

  formError.value = t(
    STATUS === HTTP_TOO_MANY_REQUESTS ? 'site.product.form.errors.tooMany' : 'site.product.form.errors.failed'
  );
}

function onSubmit() {
  formError.value = null;
  errors.value = validate();

  if (Object.keys(errors.value).length) return;

  isSending.value = true;

  SignUpForBetaService(buildPayload())
    .then(onSucceeded)
    .catch(onFailed)
    .finally(() => (isSending.value = false));
}

function onSucceeded({ data }: { data: BetaSignupResult }) {
  result.value = data;

  emit('onSuccess', data);
}
</script>

<template>
  <div
    v-if="result"
    class="flex flex-col items-start gap-4 py-6"
    data-testid="beta-success"
    role="status"
  >
    <span
      aria-hidden="true"
      class="bg-success-100 text-success-700 motion-enter flex size-12 items-center justify-center rounded-full"
    >
      <i class="pi pi-check text-xl" />
    </span>

    <h3 class="text-heading text-2xl font-semibold tracking-tight">
      {{ $t('site.product.form.success.title') }}
    </h3>

    <p class="text-body leading-relaxed">
      {{ $t('site.product.form.success.text', { company: result.company, product: platformName }) }}
    </p>
  </div>

  <div
    v-else-if="hasConsentFailed"
    class="flex flex-col items-start gap-3 py-6"
    data-testid="beta-consent-failed"
    role="alert"
  >
    <p class="text-body">
      {{ $t('site.product.form.consentFailed') }}
    </p>

    <Button
      data-testid="beta-consent-retry"
      icon="pi pi-refresh"
      severity="secondary"
      :label="$t('site.product.form.retry')"
      @click="fetchConsent"
    />
  </div>

  <form
    v-else
    novalidate
    class="flex flex-col gap-4"
    data-testid="beta-form"
    :aria-busy="isConsentLoading"
    @submit.prevent="onSubmit"
  >
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div
        v-for="{ autocomplete, field } in TEXT_FIELDS"
        :key="field"
        :class="['flex min-w-0 flex-col gap-2', { 'sm:col-span-2': field === 'company' }]"
      >
        <label
          :class="['mb-2 block text-sm', errors[field] ? 'text-danger-700' : 'text-muted']"
          :for="inputId(field)"
        >
          {{ $t(`site.product.form.${field}`) }}
        </label>

        <InputText
          v-model="form[field]"
          fluid
          :id="inputId(field)"
          :aria-describedby="errors[field] ? `${inputId(field)}-error` : undefined"
          :autocomplete
          :data-testid="`beta-${field}`"
          :invalid="!!errors[field]"
        />

        <small
          v-if="errors[field]"
          :id="`${inputId(field)}-error`"
          class="text-danger-700 text-sm"
          :data-testid="`beta-error-${field}`"
        >
          {{ errors[field] }}
        </small>
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          :class="['mb-2 block text-sm', errors.email ? 'text-danger-700' : 'text-muted']"
          :for="inputId('email')"
        >
          {{ $t('site.product.form.email') }}
        </label>

        <InputText
          v-model="form.email"
          fluid
          :id="inputId('email')"
          autocomplete="email"
          data-testid="beta-email"
          inputmode="email"
          type="email"
          :aria-describedby="errors.email ? `${inputId('email')}-error` : undefined"
          :invalid="!!errors.email"
        />

        <small
          v-if="errors.email"
          :id="`${inputId('email')}-error`"
          class="text-danger-700 text-sm"
          data-testid="beta-error-email"
        >
          {{ errors.email }}
        </small>
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          :class="['mb-2 block text-sm', errors.phone ? 'text-danger-700' : 'text-muted']"
          :for="inputId('phone')"
        >
          {{ $t('site.product.form.phone') }}
        </label>

        <InputText
          v-model="form.phone"
          fluid
          :id="inputId('phone')"
          autocomplete="tel"
          data-testid="beta-phone"
          inputmode="tel"
          type="tel"
          :aria-describedby="errors.phone ? `${inputId('phone')}-error` : undefined"
          :invalid="!!errors.phone"
          :placeholder="$t('site.product.form.phonePlaceholder')"
        />

        <small
          v-if="errors.phone"
          :id="`${inputId('phone')}-error`"
          class="text-danger-700 text-sm"
          data-testid="beta-error-phone"
        >
          {{ errors.phone }}
        </small>
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          :class="['mb-2 block text-sm', errors.city ? 'text-danger-700' : 'text-muted']"
          :for="inputId('city')"
        >
          {{ $t('site.product.form.city') }}
        </label>

        <InputText
          v-model="form.city"
          fluid
          :id="inputId('city')"
          autocomplete="address-level2"
          data-testid="beta-city"
          :aria-describedby="errors.city ? `${inputId('city')}-error` : undefined"
          :invalid="!!errors.city"
        />

        <small
          v-if="errors.city"
          :id="`${inputId('city')}-error`"
          class="text-danger-700 text-sm"
          data-testid="beta-error-city"
        >
          {{ errors.city }}
        </small>
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          :class="['mb-2 block text-sm', errors.uf ? 'text-danger-700' : 'text-muted']"
          :for="inputId('uf')"
        >
          {{ $t('site.product.form.uf') }}
        </label>

        <Select
          v-model="form.uf"
          filter
          fluid
          data-testid="beta-uf"
          optionLabel="label"
          optionValue="value"
          :inputId="inputId('uf')"
          :invalid="!!errors.uf"
          :options="BRAZIL_STATES"
          :placeholder="$t('site.product.form.ufPlaceholder')"
        />

        <small
          v-if="errors.uf"
          class="text-danger-700 text-sm"
          data-testid="beta-error-uf"
        >
          {{ errors.uf }}
        </small>
      </div>
    </div>

    <fieldset class="flex min-w-0 flex-col gap-2">
      <legend :class="['mb-2 block text-sm', errors.clients_band ? 'text-danger-700' : 'text-muted']">
        {{ $t('site.product.form.band') }}
      </legend>

      <div
        class="grid grid-cols-2 gap-3 sm:grid-cols-4"
        role="radiogroup"
        :aria-label="$t('site.product.form.band')"
      >
        <button
          v-for="band in CLIENTS_BANDS"
          :key="band"
          role="radio"
          type="button"
          :aria-checked="form.clients_band === band"
          :class="[
            'touch-target motion-quick focus-visible:outline-primary-500 rounded-md px-3 py-2.5 text-sm font-semibold ring-1 transition focus-visible:outline-2 focus-visible:outline-offset-2',
            form.clients_band === band
              ? 'bg-primary-500 text-primary-contrast ring-primary-500'
              : 'bg-panel text-body ring-line-strong hover:bg-panel-muted',
          ]"
          :data-testid="`beta-band-${band}`"
          @click="onBandPick(band)"
        >
          {{ $t(`site.product.form.bands.${band}`) }}
        </button>
      </div>

      <small
        v-if="errors.clients_band"
        class="text-danger-700 text-sm"
        data-testid="beta-error-clients_band"
      >
        {{ errors.clients_band }}
      </small>
    </fieldset>

    <div class="flex flex-col gap-2">
      <label
        :class="['mb-2 block text-sm', errors.message ? 'text-danger-700' : 'text-muted']"
        :for="inputId('message')"
      >
        {{ $t('site.product.form.message') }}
      </label>

      <Textarea
        v-model="form.message"
        autoResize
        fluid
        :id="inputId('message')"
        data-testid="beta-message"
        rows="3"
        :invalid="!!errors.message"
        :maxlength="BETA_MESSAGE_MAX_LENGTH"
      />

      <small
        v-if="errors.message"
        class="text-danger-700 text-sm"
        data-testid="beta-error-message"
      >
        {{ errors.message }}
      </small>
    </div>

    <div class="flex flex-col gap-2">
      <label
        data-testid="beta-consent"
        :class="[
          'bg-panel-muted flex cursor-pointer items-start gap-3 rounded-lg p-4 text-sm ring-1',
          errors.consent ? 'ring-danger-500' : 'ring-transparent',
        ]"
        :for="inputId('consent')"
      >
        <Checkbox
          v-model="form.consent"
          binary
          class="mt-0.5 shrink-0"
          :inputId="inputId('consent')"
          :invalid="!!errors.consent"
        />

        <Skeleton
          v-if="isConsentLoading"
          height="2.5rem"
        />

        <span
          v-else
          class="text-body leading-relaxed"
          data-testid="beta-consent-text"
        >
          {{ consent?.text }}
        </span>
      </label>

      <small
        v-if="errors.consent"
        class="text-danger-700 text-sm"
        data-testid="beta-error-consent"
      >
        {{ errors.consent }}
      </small>
    </div>

    <p
      v-if="formError"
      class="text-danger-700 text-sm"
      data-testid="beta-form-error"
      role="alert"
    >
      {{ formError }}
    </p>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <small class="text-muted text-xs">
        {{ $t('site.product.form.privacy') }}
      </small>

      <Button
        class="shrink-0"
        data-testid="beta-submit"
        icon="pi pi-arrow-right"
        iconPos="right"
        type="submit"
        :disabled="isConsentLoading"
        :label="$t('site.product.form.submit')"
        :loading="isSending"
      />
    </div>
  </form>
</template>
