<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { FirmSettings, FirmSettingsPayload, SaveFailure } from '../../interfaces';
import type { PublicFirmHours } from '@Interfaces';
import { useApiRefusal } from '../../composables';
import { FIRM_SETTINGS_LIMITS } from '../../constants';
import { GetFirmSettingsService, UpdateFirmSettingsService } from '../../services';

/*
 * The firm's contact data (rnc-4 R30, C16): address, phone, mailbox, the footer's credentials line and
 * the business hours — everything the public site prints about reaching the firm. The look and the
 * modules are the platform operator's (D25) and have no field here. One PATCH sends the whole form.
 */
type ContactField = 'address' | 'credentials' | 'mailbox' | 'phone';

const TOAST_LIFE_MS = 3500;
const TIME_PATTERN = /^\d{2}:\d{2}$/;

const toast = useToast();
const { fieldErrorsOf, formErrorOf } = useApiRefusal();
const { t } = useI18n();

const errors = ref<Record<string, string>>({});
const formError = ref<string | null>(null);
const hasFailed = ref(false);
const hours = ref<PublicFirmHours[]>([]);
const hoursErrors = ref<Record<number, string>>({});
const isLoading = ref(true);
const isSaving = ref(false);

const form = reactive<Record<ContactField, string>>({ address: '', credentials: '', mailbox: '', phone: '' });

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetFirmSettingsService()
    .then(({ data }) => fill(data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function fill(settings: FirmSettings) {
  form.address = settings.address ?? '';
  form.credentials = settings.credentials ?? '';
  form.mailbox = settings.mailbox ?? '';
  form.phone = settings.phone ?? '';
  hours.value = (settings.business_hours ?? []).map((span) => ({ ...span, weekdays: [...span.weekdays] }));
}

function spanError(span: PublicFirmHours): string | null {
  if (span.weekdays.length === 0) return t('firmSettings.hours.weekdayRequired');

  const hasTimes = TIME_PATTERN.test(span.from) && TIME_PATTERN.test(span.to);

  if (!hasTimes) return t('firmSettings.hours.timeRequired');

  return span.to > span.from ? null : t('firmSettings.hours.closesAfterOpens');
}

function validateHours(): Record<number, string> {
  return Object.fromEntries(
    hours.value
      .map((span, index) => [index, spanError(span)] as const)
      .filter((entry): entry is readonly [number, string] => entry[1] !== null)
  );
}

function hoursErrorsOf(fieldErrors: Record<string, string>): Record<number, string> {
  return Object.fromEntries(
    Object.entries(fieldErrors)
      .map(([field, message]) => [Number(/^business_hours\.(\d+)\./.exec(field)?.[1]), message] as const)
      .filter(([index]) => !Number.isNaN(index))
  );
}

function blankToNull(value: string): string | null {
  return value.trim() || null;
}

// EVENTS
function onSubmit() {
  formError.value = null;
  errors.value = {};
  hoursErrors.value = validateHours();

  if (Object.keys(hoursErrors.value).length > 0) return;

  const PAYLOAD: FirmSettingsPayload = {
    address: blankToNull(form.address),
    business_hours: hours.value,
    credentials: blankToNull(form.credentials),
    mailbox: blankToNull(form.mailbox)?.toLowerCase() ?? null,
    phone: blankToNull(form.phone),
  };

  isSaving.value = true;

  UpdateFirmSettingsService(PAYLOAD)
    .then(onSaved)
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onSaved({ data }: { data: FirmSettings }) {
  fill(data);

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('firmSettings.contact.saved') });
}

function onFailed(failure: SaveFailure) {
  const fieldErrors = fieldErrorsOf(failure);

  if (Object.keys(fieldErrors).length === 0) {
    formError.value = formErrorOf(failure);
    return;
  }

  errors.value = fieldErrors;
  hoursErrors.value = hoursErrorsOf(fieldErrors);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-building"
        title="firmSettings.contact.title"
      />
    </template>

    <p class="text-body text-sm">{{ $t('firmSettings.contact.lede') }}</p>

    <Skeleton
      v-if="isLoading"
      height="16rem"
    />

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('firmSettings.contact.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('firmSettings.retry')"
        @click="load"
      />
    </div>

    <form
      v-else
      novalidate
      class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-4 sm:p-5"
      data-testid="contact-form"
      @submit.prevent="onSubmit"
    >
      <div class="grid min-w-0 gap-4 lg:grid-cols-2">
        <div class="flex min-w-0 flex-col gap-2 lg:col-span-2">
          <label
            for="contact-address"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.address }]"
          >
            {{ $t('firmSettings.contact.address') }}
          </label>

          <InputText
            v-model="form.address"
            id="contact-address"
            autocomplete="street-address"
            class="w-full"
            data-testid="contact-address"
            :invalid="!!errors.address"
            :maxlength="FIRM_SETTINGS_LIMITS.text"
          />

          <AFormError :error="errors.address" />
        </div>

        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="contact-phone"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.phone }]"
          >
            {{ $t('firmSettings.contact.phone') }}
          </label>

          <InputText
            v-model="form.phone"
            id="contact-phone"
            autocomplete="tel"
            class="w-full"
            data-testid="contact-phone"
            inputmode="tel"
            :invalid="!!errors.phone"
            :maxlength="FIRM_SETTINGS_LIMITS.phone"
          />

          <AFormError :error="errors.phone" />
        </div>

        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="contact-mailbox"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.mailbox }]"
          >
            {{ $t('firmSettings.contact.mailbox') }}
          </label>

          <InputText
            v-model="form.mailbox"
            id="contact-mailbox"
            autocomplete="email"
            class="w-full"
            data-testid="contact-mailbox"
            inputmode="email"
            type="email"
            :invalid="!!errors.mailbox"
            :maxlength="FIRM_SETTINGS_LIMITS.text"
          />

          <AFormError
            data-testid="contact-mailbox-error"
            :error="errors.mailbox"
          />
        </div>

        <div class="flex min-w-0 flex-col gap-2 lg:col-span-2">
          <label
            for="contact-credentials"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.credentials }]"
          >
            {{ $t('firmSettings.contact.credentials') }}
          </label>

          <InputText
            v-model="form.credentials"
            id="contact-credentials"
            class="w-full"
            data-testid="contact-credentials"
            :invalid="!!errors.credentials"
            :maxlength="FIRM_SETTINGS_LIMITS.text"
          />

          <p class="text-muted text-xs">{{ $t('firmSettings.contact.credentialsHint') }}</p>

          <AFormError :error="errors.credentials" />
        </div>
      </div>

      <section class="flex min-w-0 flex-col gap-3">
        <h2 class="text-heading text-base font-semibold">{{ $t('firmSettings.hours.title') }}</h2>

        <p class="text-muted text-xs">{{ $t('firmSettings.hours.hint') }}</p>

        <MBusinessHoursEditor
          v-model="hours"
          :errors="hoursErrors"
        />

        <AFormError :error="errors.business_hours" />
      </section>

      <AFormError :error="formError" />

      <div class="flex justify-end">
        <Button
          data-testid="contact-save"
          type="submit"
          :label="$t('firmSettings.contact.save')"
          :loading="isSaving"
        />
      </div>
    </form>
  </TheLayout>
</template>
