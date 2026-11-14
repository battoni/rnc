<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PlatformFirm } from '../../../interfaces';
import { toStorageAssetPath, translateError } from '@Helpers';
import { PLATFORM_ERROR_KEYS, PLATFORM_LOGO_MAX_BYTES, PLATFORM_LOGO_TYPES } from '../../../constants';
import { RemoveFirmLogoService, UploadFirmLogoService } from '../../../services';

/*
 * A firm's logo in the console (rnc-4 R27, A43), inside the look form. It shows the logo the firm has
 * (on this origin: `/storage` is proxied per host) or says it has none; a picked file is checked the
 * way the API will — PNG, JPEG or WebP, at most 512 KB — and refused on the spot when it is not, so an
 * SVG never leaves the browser; a good one is previewed and sent on "Enviar logo". The API's own
 * refusals (sides over 1024 px, a file whose content is not what its name says) come back by code.
 * Every answer is the firm, handed up as it is: the logo is saved apart from the look's "Salvar".
 */
interface LogoFailure {
  response?: { data?: { error?: string; errors?: Record<string, string[]> } };
}

const emit = defineEmits<{
  onChange: [firm: PlatformFirm];
}>();

const props = defineProps<{
  firm: PlatformFirm;
}>();

const ACCEPT = PLATFORM_LOGO_TYPES.join(',');
const TOO_LARGE_KEY = 'platform.logoTooLarge';
const TYPE_KEY = 'platform.logoType';

const { t } = useI18n();

const error = ref<string | null>(null);
const isRemoving = ref(false);
const isUploading = ref(false);
const pickedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);

const currentLogo = computed(() => toStorageAssetPath(props.firm.logo_url));

onBeforeUnmount(dropPreview);

// HELPERS
function refusalOf(code: string): string {
  const key = PLATFORM_ERROR_KEYS[code];

  return key ? t(key) : translateError(code);
}

function refusalOfFile(file: File): string | null {
  if (!PLATFORM_LOGO_TYPES.includes(file.type)) return TYPE_KEY;

  return file.size > PLATFORM_LOGO_MAX_BYTES ? TOO_LARGE_KEY : null;
}

function dropPreview() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);

  previewUrl.value = null;
  pickedFile.value = null;
}

// EVENTS
function onPick(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;

  dropPreview();
  error.value = null;

  if (!file) return;

  const refusal = refusalOfFile(file);

  input.value = '';

  if (refusal) {
    error.value = refusalOf(refusal);
    return;
  }

  pickedFile.value = file;
  previewUrl.value = URL.createObjectURL(file);
}

function onFailed(failure: LogoFailure) {
  const code = failure?.response?.data?.errors?.logo?.[0] ?? failure?.response?.data?.error ?? 'errors.unexpected';

  error.value = refusalOf(code);
}

function onUpload() {
  if (!pickedFile.value) return;

  isUploading.value = true;
  error.value = null;

  UploadFirmLogoService(props.firm.id, pickedFile.value)
    .then(({ data }) => {
      dropPreview();
      emit('onChange', data);
    })
    .catch(onFailed)
    .finally(() => (isUploading.value = false));
}

function onRemove() {
  isRemoving.value = true;
  error.value = null;

  RemoveFirmLogoService(props.firm.id)
    .then(({ data }) => emit('onChange', data))
    .catch(onFailed)
    .finally(() => (isRemoving.value = false));
}
</script>

<template>
  <fieldset
    class="flex min-w-0 flex-col gap-3"
    data-testid="firm-logo"
  >
    <legend class="text-muted mb-2 block text-sm">{{ $t('platform.logo.label') }}</legend>

    <div class="flex flex-wrap items-center gap-4">
      <span
        class="bg-panel-muted ring-line flex h-16 w-32 shrink-0 items-center justify-center overflow-hidden rounded-md ring-1"
      >
        <img
          v-if="previewUrl"
          class="max-h-full max-w-full object-contain"
          data-testid="firm-logo-preview"
          :alt="$t('platform.logo.preview')"
          :src="previewUrl"
        />

        <img
          v-else-if="currentLogo"
          class="max-h-full max-w-full object-contain"
          data-testid="firm-logo-current"
          :alt="$t('platform.logo.current')"
          :src="currentLogo"
        />

        <i
          v-else
          aria-hidden="true"
          class="pi pi-image text-subtle text-xl"
        />
      </span>

      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <p
          v-if="!currentLogo && !previewUrl"
          class="text-muted text-xs"
          data-testid="firm-logo-empty"
        >
          {{ $t('platform.logo.empty') }}
        </p>

        <p class="text-muted text-xs">{{ $t('platform.logo.hint') }}</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <label
        class="touch-target border-line-strong text-heading hover:bg-panel-muted inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-semibold focus-within:outline-2 focus-within:outline-offset-2"
      >
        <i
          aria-hidden="true"
          class="pi pi-upload text-subtle"
        />

        {{ $t('platform.logo.choose') }}

        <input
          class="sr-only"
          data-testid="firm-logo-input"
          type="file"
          :accept="ACCEPT"
          @change="onPick"
        />
      </label>

      <Button
        v-if="pickedFile"
        data-testid="firm-logo-upload"
        icon="pi pi-check"
        size="small"
        type="button"
        :label="$t('platform.logo.upload')"
        :loading="isUploading"
        @click="onUpload"
      />

      <Button
        v-if="currentLogo && !pickedFile"
        data-testid="firm-logo-remove"
        icon="pi pi-trash"
        severity="secondary"
        size="small"
        type="button"
        :label="$t('platform.logo.remove')"
        :loading="isRemoving"
        @click="onRemove"
      />
    </div>

    <AFormError
      data-testid="firm-logo-error"
      :error
    />
  </fieldset>
</template>
