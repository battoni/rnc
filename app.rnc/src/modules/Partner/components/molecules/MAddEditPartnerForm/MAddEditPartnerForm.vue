<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Partner, PartnerPayload } from '../../../interfaces';
import { translateError } from '@Helpers';
import { PARTNER_FIELD_LIMITS, PARTNER_SPECIALTIES } from '../../../constants';
import { CreatePartnerService, UpdatePartnerService } from '../../../services';

/*
 * A partner of the network (rnc-1 R21): name, specialty, the e-mail referrals go to, phone and city.
 * `partner` null creates, a partner edits. It owns the call, shows the API's refusals by field and
 * hands the stored partner to the view (celer-07, form molecule contract).
 */
type FieldErrors = Partial<Record<keyof PartnerPayload, string>>;

interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [partner: Partner];
}>();

const props = defineProps<{
  partner?: Partner | null;
}>();

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const REQUIRED_FIELDS: (keyof PartnerPayload)[] = ['name', 'specialty', 'email'];

const { t } = useI18n();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);

const form = reactive<PartnerPayload>({
  city: props.partner?.city ?? '',
  email: props.partner?.email ?? '',
  name: props.partner?.name ?? '',
  phone: props.partner?.phone ?? '',
  specialty: props.partner?.specialty ?? '',
});

const isEdit = computed(() => !!props.partner);

const specialtyOptions = computed(() =>
  PARTNER_SPECIALTIES.map((specialty) => ({ label: t(`partners.specialties.${specialty}`), value: specialty }))
);

// HELPERS
function validate(): FieldErrors {
  const found: FieldErrors = {};

  REQUIRED_FIELDS.forEach((field) => {
    if (!String(form[field] ?? '').trim()) found[field] = t('partners.form.required');
  });

  if (!found.email && !EMAIL_PATTERN.test(form.email.trim())) found.email = t('partners.form.emailInvalid');

  return found;
}

function emptyToNull(value: string | null): string | null {
  const trimmed = (value ?? '').trim();

  return trimmed === '' ? null : trimmed;
}

// EVENTS
function onSubmit() {
  errors.value = validate();
  formError.value = null;

  if (Object.keys(errors.value).length > 0) return;

  const payload: PartnerPayload = {
    city: emptyToNull(form.city),
    email: form.email.trim(),
    name: form.name.trim(),
    phone: emptyToNull(form.phone),
    specialty: form.specialty,
  };

  isSaving.value = true;

  const request = props.partner ? UpdatePartnerService(props.partner.id, payload) : CreatePartnerService(payload);

  request
    .then((response) => emit('onSuccess', response.data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: SaveError) {
  const fieldErrors = failure.response?.data?.errors ?? {};
  const fields = Object.keys(fieldErrors);

  if (fields.length === 0) {
    formError.value = failure.translatedMessage ?? translateError('errors.unexpected');
    return;
  }

  errors.value = Object.fromEntries(fields.map((field) => [field, translateError(fieldErrors[field]?.[0] ?? '')]));
}
</script>

<template>
  <form
    novalidate
    id="partner-form"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4"
    data-testid="partner-form"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        for="partner-name"
        :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.name }]"
      >
        {{ $t('partners.form.name') }}
      </label>

      <InputText
        v-model="form.name"
        autofocus
        id="partner-name"
        class="w-full"
        data-testid="partner-name"
        :invalid="!!errors.name"
        :maxlength="PARTNER_FIELD_LIMITS.name"
        :placeholder="$t('partners.form.namePlaceholder')"
      />

      <AFormError :error="errors.name" />
    </div>

    <div class="flex flex-col gap-2">
      <label
        for="partner-specialty"
        :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.specialty }]"
      >
        {{ $t('partners.form.specialty') }}
      </label>

      <Select
        v-model="form.specialty"
        class="w-full"
        inputId="partner-specialty"
        optionLabel="label"
        optionValue="value"
        :invalid="!!errors.specialty"
        :options="specialtyOptions"
        :placeholder="$t('partners.form.specialtyPlaceholder')"
      />

      <AFormError :error="errors.specialty" />
    </div>

    <div class="flex flex-col gap-2">
      <label
        for="partner-email"
        :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.email }]"
      >
        {{ $t('partners.form.email') }}
      </label>

      <InputText
        v-model="form.email"
        id="partner-email"
        class="w-full"
        data-testid="partner-email"
        inputmode="email"
        type="email"
        :invalid="!!errors.email"
        :maxlength="PARTNER_FIELD_LIMITS.email"
      />

      <p class="text-muted text-xs">{{ $t('partners.form.emailHint') }}</p>

      <AFormError :error="errors.email" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="partner-phone"
        >
          {{ $t('partners.form.phone') }}
        </label>

        <InputText
          v-model="form.phone"
          id="partner-phone"
          class="w-full"
          data-testid="partner-phone"
          inputmode="tel"
          :invalid="!!errors.phone"
          :maxlength="PARTNER_FIELD_LIMITS.phone"
        />

        <AFormError :error="errors.phone" />
      </div>

      <div class="flex flex-col gap-2">
        <label
          class="text-muted mb-2 block text-sm"
          for="partner-city"
        >
          {{ $t('partners.form.city') }}
        </label>

        <InputText
          v-model="form.city"
          id="partner-city"
          class="w-full"
          data-testid="partner-city"
          :invalid="!!errors.city"
          :maxlength="PARTNER_FIELD_LIMITS.city"
        />

        <AFormError :error="errors.city" />
      </div>
    </div>

    <AFormError :error="formError" />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('partners.form.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="partner-save"
      form="partner-form"
      type="submit"
      :label="isEdit ? $t('partners.form.saveEdit') : $t('partners.form.saveNew')"
      :loading="isSaving"
    />
  </div>
</template>
