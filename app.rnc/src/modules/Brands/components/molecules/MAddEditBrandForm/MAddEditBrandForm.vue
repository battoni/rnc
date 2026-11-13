<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Brand, BrandPayload } from '../../../interfaces';
import { translateError } from '@Helpers';
import { brandPalette, contrastRatio } from '@SiteModule';
import {
  BRAND_COLOUR_PATTERN,
  BRAND_ERROR_KEYS,
  BRAND_FIELD_LIMITS,
  BRAND_MIN_CONTRAST,
  BRAND_PROOF_POINTS,
} from '../../../constants';
import { UpdateBrandService } from '../../../services';

/*
 * A sub-brand's edit (rnc-3 R5): its name, short name and tagline, the two colours the public site is
 * re-themed from, and the four proof points of the landing. As the primary changes, a note says how
 * white text reads on it — the API refuses under 4,5:1 (D4) — and a small strip shows the site's
 * button and highlight in the brand's own palette, as the visitor will see them. It owns the call,
 * shows the API's refusals by field and by code, and hands the stored brand to the view (celer-07).
 */
type ColourField = 'brand_accent' | 'brand_primary';
type FieldErrors = Partial<
  Record<'brand_accent' | 'brand_primary' | 'name' | 'proof' | 'short_name' | 'tagline', string>
>;

interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [brand: Brand];
}>();

const props = defineProps<{
  brand: Brand;
}>();

const WHITE = '#ffffff';

const COLOUR_FIELDS: { field: ColourField; id: string; label: string; testId: string }[] = [
  { field: 'brand_primary', id: 'brand-primary', label: 'brands.form.primary', testId: 'brand-primary' },
  { field: 'brand_accent', id: 'brand-accent', label: 'brands.form.accent', testId: 'brand-accent' },
];

const { locale, t } = useI18n();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);

const form = reactive({
  brand_accent: props.brand.brand_accent,
  brand_primary: props.brand.brand_primary,
  name: props.brand.name,
  proof: Array.from({ length: BRAND_PROOF_POINTS }, (_, index) => ({
    label: props.brand.proof[index]?.label ?? '',
    value: props.brand.proof[index]?.value ?? '',
  })),
  short_name: props.brand.short_name,
  tagline: props.brand.tagline ?? '',
});

const hasValidColours = computed(() => isColour(form.brand_primary) && isColour(form.brand_accent));
const whiteContrast = computed(() => (isColour(form.brand_primary) ? contrastRatio(WHITE, form.brand_primary) : null));
const isReadable = computed(() => whiteContrast.value !== null && whiteContrast.value >= BRAND_MIN_CONTRAST);

const ratioFormat = computed(
  () => new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1, minimumFractionDigits: 1 })
);

const contrastNote = computed(() => {
  if (whiteContrast.value === null) return t('brands.form.contrastUnknown');

  return t(isReadable.value ? 'brands.form.contrastOk' : 'brands.form.contrastLow', {
    min: ratioFormat.value.format(BRAND_MIN_CONTRAST),
    ratio: ratioFormat.value.format(whiteContrast.value),
  });
});

// The strip paints what the site will: the band from the ramp's 500 to 900, the CTA on the 300.
const preview = computed(() => {
  if (!hasValidColours.value) return null;

  const palette = brandPalette(form.brand_primary, form.brand_accent);

  return {
    band: {
      backgroundImage: `linear-gradient(135deg, ${palette['--color-primary-500']}, ${palette['--color-primary-900']})`,
    },
    cta: { backgroundColor: palette['--color-primary-300'], color: palette['--color-primary-950'] },
    lede: { color: palette['--color-primary-100'] },
    title: { color: WHITE },
  };
});

// A server refusal belongs to the value that was sent: once its field changes, it no longer holds.
(['brand_accent', 'brand_primary', 'name', 'short_name', 'tagline'] as const).forEach((field) =>
  watch(
    () => form[field],
    () => delete errors.value[field]
  )
);

watch(
  () => form.proof.map((point) => `${point.label}|${point.value}`).join('\n'),
  () => delete errors.value.proof
);

// HELPERS
function isColour(value: string): boolean {
  return BRAND_COLOUR_PATTERN.test(value.trim());
}

function validate(): FieldErrors {
  const found: FieldErrors = {};

  if (!form.name.trim()) found.name = t('brands.form.required');

  if (!form.short_name.trim()) found.short_name = t('brands.form.required');

  COLOUR_FIELDS.forEach(({ field }) => {
    if (!isColour(form[field])) found[field] = t('brands.form.invalidColour');
  });

  if (form.proof.some((point) => !point.value.trim() || !point.label.trim()))
    found.proof = t('brands.form.proofRequired');

  return found;
}

function translateCode(code: string): string {
  const key = BRAND_ERROR_KEYS[code];

  return key ? t(key, { min: ratioFormat.value.format(BRAND_MIN_CONTRAST) }) : translateError(code);
}

// EVENTS
function onColourPick(field: ColourField, event: Event) {
  form[field] = (event.target as HTMLInputElement).value;
}

function onSubmit() {
  errors.value = validate();
  formError.value = null;

  if (Object.keys(errors.value).length > 0) return;

  const payload: BrandPayload = {
    brand_accent: form.brand_accent.trim().toLowerCase(),
    brand_primary: form.brand_primary.trim().toLowerCase(),
    name: form.name.trim(),
    proof: form.proof.map((point) => ({ label: point.label.trim(), value: point.value.trim() })),
    short_name: form.short_name.trim(),
    tagline: form.tagline.trim() || null,
  };

  isSaving.value = true;

  UpdateBrandService(props.brand.id, payload)
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

  errors.value = Object.fromEntries(
    fields.map((field) => [field.startsWith('proof') ? 'proof' : field, translateCode(fieldErrors[field]?.[0] ?? '')])
  );
}
</script>

<template>
  <form
    novalidate
    id="brand-form"
    class="mx-auto flex w-full max-w-[560px] flex-col gap-5"
    data-testid="brand-form"
    @submit.prevent="onSubmit"
  >
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="brand-name"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.name }]"
        >
          {{ $t('brands.form.name') }}
        </label>

        <InputText
          v-model="form.name"
          autofocus
          id="brand-name"
          class="w-full"
          data-testid="brand-name"
          :invalid="!!errors.name"
          :maxlength="BRAND_FIELD_LIMITS.name"
        />

        <AFormError :error="errors.name" />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="brand-short-name"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.short_name }]"
        >
          {{ $t('brands.form.shortName') }}
        </label>

        <InputText
          v-model="form.short_name"
          id="brand-short-name"
          class="w-full"
          data-testid="brand-short-name"
          :invalid="!!errors.short_name"
          :maxlength="BRAND_FIELD_LIMITS.shortName"
        />

        <AFormError :error="errors.short_name" />
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <label
        for="brand-tagline"
        :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.tagline }]"
      >
        {{ $t('brands.form.tagline') }}
      </label>

      <InputText
        v-model="form.tagline"
        id="brand-tagline"
        class="w-full"
        data-testid="brand-tagline"
        :invalid="!!errors.tagline"
        :maxlength="BRAND_FIELD_LIMITS.tagline"
      />

      <p class="text-muted text-xs">{{ $t('brands.form.taglineHint') }}</p>

      <AFormError :error="errors.tagline" />
    </div>

    <fieldset class="flex min-w-0 flex-col gap-3">
      <legend class="text-heading mb-1 text-sm font-semibold">{{ $t('brands.form.colours') }}</legend>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div
          v-for="colour in COLOUR_FIELDS"
          class="flex min-w-0 flex-col gap-2"
          :key="colour.field"
        >
          <label
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors[colour.field] }]"
            :for="colour.id"
          >
            {{ $t(colour.label) }}
          </label>

          <div class="flex items-center gap-2">
            <input
              class="border-line-strong h-10 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
              type="color"
              :aria-label="$t('brands.form.pick', { field: $t(colour.label) })"
              :value="isColour(form[colour.field]) ? form[colour.field].toLowerCase() : '#000000'"
              @input="onColourPick(colour.field, $event)"
            />

            <InputText
              v-model="form[colour.field]"
              :id="colour.id"
              class="w-full font-mono"
              maxlength="7"
              spellcheck="false"
              :data-testid="colour.testId"
              :invalid="!!errors[colour.field]"
            />
          </div>

          <AFormError
            :data-testid="`${colour.testId}-error`"
            :error="errors[colour.field]"
          />
        </div>
      </div>

      <p
        aria-live="polite"
        data-testid="brand-contrast-note"
        :class="[
          'flex items-start gap-2 rounded-md px-3 py-2 text-sm',
          isReadable ? 'bg-success-50 text-success-800' : 'bg-danger-50 text-danger-800',
        ]"
        :data-readable="String(isReadable)"
      >
        <i
          aria-hidden="true"
          :class="['pi mt-0.5 text-sm', isReadable ? 'pi-check-circle' : 'pi-exclamation-triangle']"
        />

        {{ contrastNote }}
      </p>

      <div
        v-if="preview"
        aria-hidden="true"
        class="ring-line flex items-center justify-between gap-3 overflow-hidden rounded-lg p-4 ring-1"
        data-testid="brand-preview-strip"
        :style="preview.band"
      >
        <span class="flex min-w-0 flex-col gap-0.5">
          <strong
            class="truncate text-base font-semibold"
            :style="preview.title"
          >
            {{ form.short_name || form.name }}
          </strong>

          <span
            class="truncate text-xs"
            :style="preview.lede"
          >
            {{ form.tagline || $t('brands.form.previewLede') }}
          </span>
        </span>

        <span
          class="shrink-0 rounded-md px-3 py-1.5 text-xs font-semibold"
          :style="preview.cta"
        >
          {{ $t('brands.form.previewCta') }}
        </span>
      </div>
    </fieldset>

    <fieldset class="flex min-w-0 flex-col gap-3">
      <legend class="text-heading mb-1 text-sm font-semibold">{{ $t('brands.form.proof') }}</legend>

      <p class="text-muted text-xs">{{ $t('brands.form.proofHint') }}</p>

      <div
        v-for="(point, index) in form.proof"
        class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.6fr)] gap-3"
        :key="index"
      >
        <InputText
          v-model="point.value"
          class="w-full font-semibold"
          data-testid="brand-proof-value"
          :aria-label="$t('brands.form.proofValue', { n: index + 1 })"
          :invalid="!!errors.proof && !point.value.trim()"
          :maxlength="BRAND_FIELD_LIMITS.proofValue"
          :placeholder="$t('brands.form.proofValuePlaceholder')"
        />

        <InputText
          v-model="point.label"
          class="w-full"
          data-testid="brand-proof-label"
          :aria-label="$t('brands.form.proofLabel', { n: index + 1 })"
          :invalid="!!errors.proof && !point.label.trim()"
          :maxlength="BRAND_FIELD_LIMITS.proofLabel"
          :placeholder="$t('brands.form.proofLabelPlaceholder')"
        />
      </div>

      <AFormError
        data-testid="brand-proof-error"
        :error="errors.proof"
      />
    </fieldset>

    <AFormError :error="formError" />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[560px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('brands.form.cancel')"
      @click="emit('onClose')"
    />

    <Button
      data-testid="brand-save"
      form="brand-form"
      type="submit"
      :label="$t('brands.form.save')"
      :loading="isSaving"
    />
  </div>
</template>
