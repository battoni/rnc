<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { translateError } from '@Helpers';
import { PLATFORM_CONSENT_MAX_LENGTH, PLATFORM_CONSENT_PLACEHOLDER, PLATFORM_ERROR_KEYS } from '../../constants';
import { GetConsentDefaultService, UpdateConsentDefaultService } from '../../services';

/*
 * The platform's default consent (rnc-4 R13, R25, C15): the sentence the NEXT firm's version 1
 * copies, each firm's name filled in where `{escritorio}` stands. A sentence without it names nobody
 * and is refused here as the API refuses it (`platform.consentPlaceholderMissing`). Existing firms
 * keep their own versions — saving here never touches them.
 */
interface SaveError {
  response?: { data?: { errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const TOAST_LIFE_MS = 3500;

const toast = useToast();
const { t } = useI18n();

const error = ref<string | null>(null);
const hasFailed = ref(false);
const isLoading = ref(true);
const isSaving = ref(false);
const text = ref('');

const counter = computed(() =>
  t('platform.consent.counter', { count: text.value.length, max: PLATFORM_CONSENT_MAX_LENGTH })
);

watch(text, onTextChange);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetConsentDefaultService()
    .then(({ data }) => (text.value = data.text))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function validate(value: string): string | null {
  if (!value) return t('platform.errors.required');

  if (!value.includes(PLATFORM_CONSENT_PLACEHOLDER)) return t('platform.errors.placeholderMissing');

  return null;
}

// EVENTS
function onTextChange() {
  error.value = null;
}

function onSubmit() {
  const value = text.value.trim();

  error.value = validate(value);

  if (error.value) return;

  isSaving.value = true;

  UpdateConsentDefaultService({ text: value })
    .then(onSaved)
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onSaved({ data }: { data: { text: string } }) {
  text.value = data.text;

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('platform.consent.saved') });
}

function onFailed(failure: SaveError) {
  const code = failure.response?.data?.errors?.text?.[0];

  if (!code) {
    error.value = failure.translatedMessage ?? translateError('errors.unexpected');
    return;
  }

  const key = PLATFORM_ERROR_KEYS[code];

  error.value = key ? t(key) : translateError(code);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-file-check"
        title="platform.consent.title"
      />
    </template>

    <p
      class="text-body text-sm"
      data-testid="consent-lede"
    >
      {{ $t('platform.consent.lede', { placeholder: PLATFORM_CONSENT_PLACEHOLDER }) }}
    </p>

    <Skeleton
      v-if="isLoading"
      height="10rem"
    />

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('platform.consent.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('platform.firms.retry')"
        @click="load"
      />
    </div>

    <form
      v-else
      novalidate
      class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-4 sm:p-5"
      data-testid="consent-form"
      @submit.prevent="onSubmit"
    >
      <div class="flex flex-col gap-2">
        <label
          for="consent-text"
          :class="['text-muted mb-2 block text-sm', { 'text-danger-700': error }]"
        >
          {{ $t('platform.consent.label') }}
        </label>

        <Textarea
          v-model="text"
          autoResize
          id="consent-text"
          class="w-full"
          data-testid="consent-text"
          rows="5"
          :invalid="!!error"
          :maxlength="PLATFORM_CONSENT_MAX_LENGTH"
        />

        <p class="text-muted flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs">
          <span>{{ $t('platform.consent.hint', { placeholder: PLATFORM_CONSENT_PLACEHOLDER }) }}</span>

          <span class="tabular-nums">{{ counter }}</span>
        </p>

        <AFormError
          data-testid="consent-error"
          :error
        />
      </div>

      <div class="flex justify-end">
        <Button
          data-testid="consent-save"
          type="submit"
          :label="$t('platform.form.save')"
          :loading="isSaving"
        />
      </div>
    </form>
  </TheLayout>
</template>
