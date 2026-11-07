<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ChannelBoard, ChannelDefaults, ChannelFirm, ChannelFirmPayload } from '../../../interfaces';
import { formatReal, toCents, translateError } from '@Helpers';
import {
  BASIS_POINTS_PER_PERCENT,
  CHANNEL_FEE_BOUNDS,
  CHANNEL_FIELD_LIMITS,
  CHANNEL_SHARE_BOUNDS,
  channelShareOf,
} from '../../../constants';
import { AddChannelFirmService, UpdateChannelFirmService } from '../../../services';

/*
 * The operator's add and edit of an RNC firm on the channel (rnc-3 R7, AM9): its name and city, the
 * platform's monthly fee, Franco's share and the operator's notes. A new firm starts on the board's
 * defaults and always at contato — the stage moves only by "Avançar". As the fee or the share change,
 * a line says what Franco keeps once the firm is active, rounded half-up as the API does. The bounds
 * are refused here before the API refuses them. It owns the call and hands the whole board to the
 * view (W41, celer-07).
 */
type Field = 'city' | 'monthly_fee_cents' | 'name' | 'share_bp';
type FieldErrors = Partial<Record<Field, string>>;

interface FormState {
  city: string;
  fee: number | null;
  name: string;
  notes: string;
  share: number | null;
}

interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [board: ChannelBoard, name: string];
}>();

const props = defineProps<{
  defaults: ChannelDefaults;
  firm?: ChannelFirm;
}>();

const NUMBER_LOCALE = 'pt-BR';
const SHARE_FRACTION_DIGITS = 2;

const { t } = useI18n();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);

const form = reactive<FormState>({
  city: props.firm?.city ?? '',
  fee: (props.firm?.monthly_fee_cents ?? props.defaults.monthly_fee_cents) / 100,
  name: props.firm?.name ?? '',
  notes: props.firm?.notes ?? '',
  share: (props.firm?.share_bp ?? props.defaults.share_bp) / BASIS_POINTS_PER_PERCENT,
});

const feeCents = computed(() => (form.fee === null ? null : toCents(form.fee)));
const isEditing = computed(() => props.firm !== undefined);
const shareBp = computed(() => (form.share === null ? null : Math.round(form.share * BASIS_POINTS_PER_PERCENT)));

const feeBoundsMessage = computed(() =>
  t('channel.form.feeBounds', { max: formatReal(CHANNEL_FEE_BOUNDS.max), min: formatReal(CHANNEL_FEE_BOUNDS.min) })
);

const shareBoundsMessage = computed(() =>
  t('channel.form.shareBounds', {
    max: CHANNEL_SHARE_BOUNDS.max / BASIS_POINTS_PER_PERCENT,
    min: CHANNEL_SHARE_BOUNDS.min / BASIS_POINTS_PER_PERCENT,
  })
);

const sharePreview = computed(() => {
  if (feeCents.value === null || shareBp.value === null) return null;

  return t('channel.form.preview', { amount: formatReal(channelShareOf(feeCents.value, shareBp.value)) });
});

// HELPERS
function isWithin(value: number | null, bounds: { max: number; min: number }): boolean {
  return value !== null && value >= bounds.min && value <= bounds.max;
}

function messageFor(field: string, code: string): string {
  if (field === 'monthly_fee_cents') return feeBoundsMessage.value;

  if (field === 'share_bp') return shareBoundsMessage.value;

  return translateError(code);
}

function validate(): FieldErrors {
  const found: FieldErrors = {};

  if (!form.name.trim()) found.name = t('channel.form.required');

  if (!form.city.trim()) found.city = t('channel.form.required');

  if (!isWithin(feeCents.value, CHANNEL_FEE_BOUNDS)) found.monthly_fee_cents = feeBoundsMessage.value;

  if (!isWithin(shareBp.value, CHANNEL_SHARE_BOUNDS)) found.share_bp = shareBoundsMessage.value;

  return found;
}

// EVENTS
function onSubmit() {
  errors.value = validate();
  formError.value = null;

  if (Object.keys(errors.value).length > 0) return;

  const payload: ChannelFirmPayload = {
    city: form.city.trim(),
    monthly_fee_cents: feeCents.value ?? 0,
    name: form.name.trim(),
    notes: form.notes.trim() || null,
    share_bp: shareBp.value ?? 0,
  };

  isSaving.value = true;

  const request = props.firm ? UpdateChannelFirmService(props.firm.id, payload) : AddChannelFirmService(payload);

  request
    .then((response) => emit('onSuccess', response.data, payload.name))
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

  errors.value = Object.fromEntries(fields.map((field) => [field, messageFor(field, fieldErrors[field]?.[0] ?? '')]));
}
</script>

<template>
  <form
    novalidate
    id="channel-firm-form"
    class="mx-auto flex w-full max-w-[520px] flex-col gap-4"
    data-testid="channel-form"
    @submit.prevent="onSubmit"
  >
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="channel-name"
          :class="['mb-2 block text-sm', errors.name ? 'text-danger-700' : 'text-muted']"
        >
          {{ $t('channel.form.name') }}
        </label>

        <InputText
          v-model="form.name"
          autofocus
          id="channel-name"
          class="w-full"
          data-testid="channel-name"
          :invalid="!!errors.name"
          :maxlength="CHANNEL_FIELD_LIMITS.name"
        />

        <AFormError :error="errors.name" />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="channel-city"
          :class="['mb-2 block text-sm', errors.city ? 'text-danger-700' : 'text-muted']"
        >
          {{ $t('channel.form.city') }}
        </label>

        <InputText
          v-model="form.city"
          id="channel-city"
          class="w-full"
          data-testid="channel-city"
          :invalid="!!errors.city"
          :maxlength="CHANNEL_FIELD_LIMITS.city"
        />

        <AFormError :error="errors.city" />
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="channel-fee"
          :class="['mb-2 block text-sm', errors.monthly_fee_cents ? 'text-danger-700' : 'text-muted']"
        >
          {{ $t('channel.form.fee') }}
        </label>

        <InputNumber
          v-model="form.fee"
          fluid
          currency="BRL"
          data-testid="channel-fee"
          inputId="channel-fee"
          mode="currency"
          :invalid="!!errors.monthly_fee_cents"
          :locale="NUMBER_LOCALE"
        />

        <AFormError :error="errors.monthly_fee_cents" />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="channel-share-rate"
          :class="['mb-2 block text-sm', errors.share_bp ? 'text-danger-700' : 'text-muted']"
        >
          {{ $t('channel.form.share') }}
        </label>

        <InputNumber
          v-model="form.share"
          fluid
          data-testid="channel-share-rate"
          inputId="channel-share-rate"
          suffix="%"
          :invalid="!!errors.share_bp"
          :locale="NUMBER_LOCALE"
          :maxFractionDigits="SHARE_FRACTION_DIGITS"
        />

        <AFormError :error="errors.share_bp" />
      </div>
    </div>

    <p
      v-if="sharePreview"
      aria-live="polite"
      class="bg-primary-50 text-primary-950 rounded-md px-3 py-2 text-sm font-semibold tabular-nums"
      data-testid="channel-share-preview"
    >
      {{ sharePreview }}
    </p>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="channel-notes"
      >
        {{ $t('channel.form.notes') }}
      </label>

      <Textarea
        v-model="form.notes"
        autoResize
        id="channel-notes"
        class="w-full"
        data-testid="channel-notes"
        rows="3"
        :maxlength="CHANNEL_FIELD_LIMITS.notes"
      />

      <span class="text-muted text-xs">{{ $t('channel.form.notesHint') }}</span>
    </div>

    <p
      v-if="!isEditing"
      class="text-muted text-sm"
    >
      {{ $t('channel.form.startsAt') }}
    </p>

    <AFormError :error="formError" />

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <Button
        severity="secondary"
        type="button"
        :label="$t('channel.form.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="channel-save"
        type="submit"
        :label="$t('channel.form.save')"
        :loading="isSaving"
      />
    </div>
  </form>
</template>
