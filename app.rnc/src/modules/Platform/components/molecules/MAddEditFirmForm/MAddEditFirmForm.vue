<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CreateFirmPayload, PlatformFirm, ProvisionedFirm, UpdateFirmLookPayload } from '../../../interfaces';
import { translateError } from '@Helpers';
import { brandPalette, contrastRatio } from '@SiteModule';
import {
  PLATFORM_COLOUR_PATTERN,
  PLATFORM_ERROR_KEYS,
  PLATFORM_FIRM_LIMITS,
  PLATFORM_MIN_CONTRAST,
  PLATFORM_PROOF_POINTS,
} from '../../../constants';
import { CreatePlatformFirmService, UpdatePlatformFirmService } from '../../../services';

/*
 * The console's firm form (rnc-4 R25), generalised from rnc-3's sub-brand form. Without a firm it
 * makes one: name, slug — with the address it will answer on as it is typed — and the manager's
 * login; the slug's rules are the API's (format, reserved, taken: `FirmProvisioner`), shown on the
 * field. The new firm is handed to the view at once and the password stays here, shown once (W57).
 * With a firm it edits its look: names, tagline, the four proof points and the two colours, with a
 * live note on how white reads on the primary — the API refuses under 4,5:1 (R11) — and the logo
 * (`MFirmLogoField`, R27), saved on its own: its answer goes up as `onLogoChange`, the dialog stays.
 */
type ColourField = 'brand_accent' | 'brand_primary';
type FieldName =
  | 'brand_accent'
  | 'brand_primary'
  | 'email'
  | 'logo_text'
  | 'manager'
  | 'name'
  | 'proof'
  | 'short_name'
  | 'slug'
  | 'tagline';
type FieldErrors = Partial<Record<FieldName, string>>;

interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onLogoChange: [firm: PlatformFirm];
  onSuccess: [firm: PlatformFirm];
}>();

const props = defineProps<{
  firm: PlatformFirm | null;
}>();

const WHITE = '#ffffff';

const COLOUR_FIELDS: { field: ColourField; id: string; label: string; testId: string }[] = [
  { field: 'brand_primary', id: 'firm-primary', label: 'platform.form.primary', testId: 'firm-primary' },
  { field: 'brand_accent', id: 'firm-accent', label: 'platform.form.accent', testId: 'firm-accent' },
];

const WATCHED_FIELDS = [
  'brand_accent',
  'brand_primary',
  'email',
  'logo_text',
  'manager',
  'name',
  'short_name',
  'slug',
  'tagline',
] as const;

const { locale, t } = useI18n();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);
const provisioned = ref<ProvisionedFirm | null>(null);

const form = reactive({
  brand_accent: props.firm?.brand_accent ?? '',
  brand_primary: props.firm?.brand_primary ?? '',
  email: '',
  logo_text: props.firm?.logo_text ?? '',
  manager: '',
  name: props.firm?.name ?? '',
  proof: Array.from({ length: PLATFORM_PROOF_POINTS }, (_, index) => ({
    label: props.firm?.proof?.[index]?.label ?? '',
    value: props.firm?.proof?.[index]?.value ?? '',
  })),
  short_name: props.firm?.short_name ?? '',
  slug: '',
  tagline: props.firm?.tagline ?? '',
});

const isEditMode = computed(() => !!props.firm);
const slugPreview = computed(() => (form.slug.trim() ? siteAddressOf(form.slug.trim().toLowerCase()) : ''));
const whiteContrast = computed(() => (isColour(form.brand_primary) ? contrastRatio(WHITE, form.brand_primary) : null));
const isReadable = computed(() => whiteContrast.value !== null && whiteContrast.value >= PLATFORM_MIN_CONTRAST);

const ratioFormat = computed(
  () => new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1, minimumFractionDigits: 1 })
);

const contrastNote = computed(() => {
  if (whiteContrast.value === null) return t('platform.form.contrastUnknown');

  return t(isReadable.value ? 'platform.form.contrastOk' : 'platform.form.contrastLow', {
    min: ratioFormat.value.format(PLATFORM_MIN_CONTRAST),
    ratio: ratioFormat.value.format(whiteContrast.value),
  });
});

// The strip paints what the firm's site will: the band from the ramp's 500 to 900, the CTA on the 300.
const preview = computed(() => {
  if (!isColour(form.brand_primary) || !isColour(form.brand_accent)) return null;

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
WATCHED_FIELDS.forEach((field) =>
  watch(
    () => form[field],
    () => delete errors.value[field]
  )
);

watch(() => form.proof.map((point) => `${point.label}|${point.value}`).join('\n'), onProofChange);

// HELPERS
function isColour(value: string): boolean {
  return PLATFORM_COLOUR_PATTERN.test(value.trim());
}

function siteAddressOf(slug: string): string {
  return `${window.location.protocol}//${slug}.${window.location.host}`;
}

function filledProof(): boolean {
  return form.proof.some((point) => point.value.trim() || point.label.trim());
}

function validateNew(): FieldErrors {
  const found: FieldErrors = {};

  if (!form.name.trim()) found.name = t('platform.errors.required');

  if (!form.slug.trim()) found.slug = t('platform.errors.required');

  if (!form.manager.trim()) found.manager = t('platform.errors.required');

  return found;
}

function validateLook(): FieldErrors {
  const found: FieldErrors = {};

  (['name', 'logo_text', 'short_name'] as const).forEach((field) => {
    if (!form[field].trim()) found[field] = t('platform.errors.required');
  });

  COLOUR_FIELDS.forEach(({ field }) => {
    if (!isColour(form[field])) found[field] = t('platform.form.invalidColour');
  });

  const isHalfFilled = filledProof() && form.proof.some((point) => !point.value.trim() || !point.label.trim());

  if (isHalfFilled) found.proof = t('platform.form.proofRequired');

  return found;
}

function translateCode(code: string): string {
  const key = PLATFORM_ERROR_KEYS[code];

  return key ? t(key, { min: ratioFormat.value.format(PLATFORM_MIN_CONTRAST) }) : translateError(code);
}

function newPayload(): CreateFirmPayload {
  return {
    email: form.email.trim().toLowerCase() || null,
    manager: form.manager.trim().toLowerCase(),
    name: form.name.trim(),
    slug: form.slug.trim().toLowerCase(),
  };
}

function lookPayload(): UpdateFirmLookPayload {
  return {
    brand_accent: form.brand_accent.trim().toLowerCase(),
    brand_primary: form.brand_primary.trim().toLowerCase(),
    logo_text: form.logo_text.trim(),
    name: form.name.trim(),
    proof: filledProof() ? form.proof.map((point) => ({ label: point.label.trim(), value: point.value.trim() })) : null,
    short_name: form.short_name.trim(),
    tagline: form.tagline.trim() || null,
  };
}

// EVENTS
function onProofChange() {
  delete errors.value.proof;
}

function onColourPick(field: ColourField, event: Event) {
  form[field] = (event.target as HTMLInputElement).value;
}

function onSubmit() {
  errors.value = props.firm ? validateLook() : validateNew();
  formError.value = null;

  if (Object.keys(errors.value).length > 0) return;

  isSaving.value = true;

  const request = props.firm
    ? UpdatePlatformFirmService(props.firm.id, lookPayload()).then((response) => emit('onSuccess', response.data))
    : CreatePlatformFirmService(newPayload()).then((response) => onCreated(response.data));

  request.catch(onFailed).finally(() => (isSaving.value = false));
}

function onCreated(created: ProvisionedFirm) {
  provisioned.value = created;

  emit('onSuccess', created.firm);
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
  <div
    v-if="provisioned"
    class="mx-auto flex w-full max-w-[560px] flex-col gap-4"
  >
    <p
      class="text-body text-sm break-words"
      data-testid="firm-created"
    >
      {{
        $t('platform.form.created', {
          manager: provisioned.manager.username,
          name: provisioned.firm.name,
          site: provisioned.firm.site_url,
        })
      }}
    </p>

    <MOneTimePassword :password="provisioned.password" />
  </div>

  <form
    v-else
    novalidate
    id="firm-form"
    class="mx-auto flex w-full max-w-[560px] flex-col gap-5"
    data-testid="firm-form"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        for="firm-name"
        :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.name }]"
      >
        {{ $t('platform.form.name') }}
      </label>

      <InputText
        v-model="form.name"
        autofocus
        id="firm-name"
        class="w-full"
        data-testid="firm-name"
        :invalid="!!errors.name"
        :maxlength="PLATFORM_FIRM_LIMITS.name"
      />

      <AFormError
        data-testid="firm-name-error"
        :error="errors.name"
      />
    </div>

    <template v-if="!isEditMode">
      <div class="flex flex-col gap-2">
        <label
          for="firm-slug"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.slug }]"
        >
          {{ $t('platform.form.slug') }}
        </label>

        <InputText
          v-model="form.slug"
          id="firm-slug"
          autocapitalize="none"
          autocomplete="off"
          class="w-full font-mono"
          data-testid="firm-slug"
          spellcheck="false"
          :invalid="!!errors.slug"
        />

        <p
          class="text-muted text-xs break-all"
          data-testid="firm-slug-preview"
        >
          {{ slugPreview ? $t('platform.form.slugPreview', { site: slugPreview }) : $t('platform.form.slugHint') }}
        </p>

        <AFormError
          data-testid="firm-slug-error"
          :error="errors.slug"
        />
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="firm-manager"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.manager }]"
          >
            {{ $t('platform.form.manager') }}
          </label>

          <InputText
            v-model="form.manager"
            id="firm-manager"
            autocapitalize="none"
            autocomplete="off"
            class="w-full"
            data-testid="firm-manager"
            :invalid="!!errors.manager"
            :placeholder="$t('platform.form.managerPlaceholder')"
          />

          <AFormError
            data-testid="firm-manager-error"
            :error="errors.manager"
          />
        </div>

        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="firm-email"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.email }]"
          >
            {{ $t('platform.form.email') }}
          </label>

          <InputText
            v-model="form.email"
            id="firm-email"
            class="w-full"
            data-testid="firm-email"
            inputmode="email"
            type="email"
            :invalid="!!errors.email"
          />

          <AFormError
            data-testid="firm-email-error"
            :error="errors.email"
          />
        </div>
      </div>

      <p class="text-muted text-xs">{{ $t('platform.form.newNote') }}</p>
    </template>

    <template v-else>
      <MFirmLogoField
        v-if="props.firm"
        :firm="props.firm"
        @onChange="emit('onLogoChange', $event)"
      />

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="firm-logo-text"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.logo_text }]"
          >
            {{ $t('platform.form.logoText') }}
          </label>

          <InputText
            v-model="form.logo_text"
            id="firm-logo-text"
            class="w-full"
            data-testid="firm-logo-text"
            :invalid="!!errors.logo_text"
            :maxlength="PLATFORM_FIRM_LIMITS.logoText"
          />

          <AFormError :error="errors.logo_text" />
        </div>

        <div class="flex min-w-0 flex-col gap-2">
          <label
            for="firm-short-name"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.short_name }]"
          >
            {{ $t('platform.form.shortName') }}
          </label>

          <InputText
            v-model="form.short_name"
            id="firm-short-name"
            class="w-full"
            data-testid="firm-short-name"
            :invalid="!!errors.short_name"
            :maxlength="PLATFORM_FIRM_LIMITS.shortName"
          />

          <AFormError :error="errors.short_name" />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label
          for="firm-tagline"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': errors.tagline }]"
        >
          {{ $t('platform.form.tagline') }}
        </label>

        <InputText
          v-model="form.tagline"
          id="firm-tagline"
          class="w-full"
          data-testid="firm-tagline"
          :invalid="!!errors.tagline"
          :maxlength="PLATFORM_FIRM_LIMITS.tagline"
        />

        <AFormError :error="errors.tagline" />
      </div>

      <fieldset class="flex min-w-0 flex-col gap-3">
        <legend class="text-heading mb-1 text-sm font-semibold">{{ $t('platform.form.colours') }}</legend>

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

            <div class="flex items-center gap-3">
              <input
                class="border-line-strong h-10 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                type="color"
                :aria-label="$t('platform.form.pick', { field: $t(colour.label) })"
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
          data-testid="firm-contrast-note"
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
          data-testid="firm-preview-strip"
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
              {{ form.tagline || $t('platform.form.previewLede') }}
            </span>
          </span>

          <span
            class="shrink-0 rounded-md px-3 py-1.5 text-xs font-semibold"
            :style="preview.cta"
          >
            {{ $t('platform.form.previewCta') }}
          </span>
        </div>
      </fieldset>

      <fieldset class="flex min-w-0 flex-col gap-3">
        <legend class="text-heading mb-1 text-sm font-semibold">{{ $t('platform.form.proof') }}</legend>

        <p class="text-muted text-xs">{{ $t('platform.form.proofHint') }}</p>

        <div
          v-for="(point, index) in form.proof"
          class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.6fr)] gap-3"
          :key="index"
        >
          <InputText
            v-model="point.value"
            class="w-full font-semibold"
            data-testid="firm-proof-value"
            :aria-label="$t('platform.form.proofValue', { n: index + 1 })"
            :invalid="!!errors.proof && !point.value.trim()"
            :maxlength="PLATFORM_FIRM_LIMITS.proofValue"
            :placeholder="$t('platform.form.proofValuePlaceholder')"
          />

          <InputText
            v-model="point.label"
            class="w-full"
            data-testid="firm-proof-label"
            :aria-label="$t('platform.form.proofLabel', { n: index + 1 })"
            :invalid="!!errors.proof && !point.label.trim()"
            :maxlength="PLATFORM_FIRM_LIMITS.proofLabel"
            :placeholder="$t('platform.form.proofLabelPlaceholder')"
          />
        </div>

        <AFormError
          data-testid="firm-proof-error"
          :error="errors.proof"
        />
      </fieldset>
    </template>

    <AFormError :error="formError" />
  </form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[560px] shrink-0 justify-end gap-3 py-4">
    <Button
      v-if="provisioned"
      data-testid="firm-done"
      :label="$t('platform.form.done')"
      @click="emit('onClose')"
    />

    <template v-else>
      <Button
        severity="secondary"
        :label="$t('platform.form.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="firm-save"
        form="firm-form"
        type="submit"
        :label="isEditMode ? $t('platform.form.save') : $t('platform.form.create')"
        :loading="isSaving"
      />
    </template>
  </div>
</template>
