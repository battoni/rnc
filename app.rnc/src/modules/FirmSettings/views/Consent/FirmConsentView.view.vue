<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { ConsentVersion, SaveFailure } from '../../interfaces';
import { useUserStore } from '@UserModule';
import { useApiRefusal } from '../../composables';
import { FIRM_CONSENT_MAX_LENGTH, FIRM_CONSENT_PLACEHOLDER } from '../../constants';
import { CreateConsentVersionService, GetConsentVersionsService } from '../../services';

/*
 * The firm's consent (rnc-4 R33, W79): the version the diagnostic shows today, a new one written with
 * a live preview — `{escritorio}` becomes the firm's name, as every visitor will read it — and the
 * history (version, date, author). Saving makes version n+1; answers already stored keep the version
 * they were given under, so nothing here rewrites the past.
 */
const DATE_FORMAT: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit', year: 'numeric' };
const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const toast = useToast();
const { fieldErrorsOf, formErrorOf } = useApiRefusal();
const { locale, t } = useI18n();

const error = ref<string | null>(null);
const hasFailed = ref(false);
const isLoading = ref(true);
const isSaving = ref(false);
const text = ref('');
const versions = ref<ConsentVersion[]>([]);

const current = computed(() => versions.value[0] ?? null);
const dateFormat = computed(() => new Intl.DateTimeFormat(locale.value, DATE_FORMAT));
const firmName = computed(() => userStore.user?.firm?.name ?? '');
const preview = computed(() => text.value.trim().split(FIRM_CONSENT_PLACEHOLDER).join(firmName.value));

const counter = computed(() =>
  t('firmSettings.consent.counter', { count: text.value.length, max: FIRM_CONSENT_MAX_LENGTH })
);

watch(text, onTextChange);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetConsentVersionsService()
    .then(onLoaded)
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function dateOf(version: ConsentVersion): string {
  return dateFormat.value.format(new Date(version.created_at));
}

function authorOf(version: ConsentVersion): string {
  return version.author?.name ?? t('firmSettings.consent.platformAuthor');
}

function validate(value: string): string | null {
  if (!value) return t('firmSettings.errors.required');

  if (!value.includes(FIRM_CONSENT_PLACEHOLDER)) return t('leads.consentPlaceholderMissing');

  return value === current.value?.text ? t('firmSettings.consent.unchanged') : null;
}

// EVENTS
function onLoaded({ data }: { data: ConsentVersion[] }) {
  versions.value = data;
  text.value = data[0]?.text ?? '';
}

function onTextChange() {
  error.value = null;
}

function onSubmit() {
  const value = text.value.trim();

  error.value = validate(value);

  if (error.value) return;

  isSaving.value = true;

  CreateConsentVersionService({ text: value })
    .then(onSaved)
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onSaved({ data }: { data: ConsentVersion }) {
  versions.value = [data, ...versions.value];
  text.value = data.text;

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('firmSettings.consent.saved', { version: data.version }),
  });
}

function onFailed(failure: SaveFailure) {
  const errors = fieldErrorsOf(failure);

  error.value = errors.text ?? formErrorOf(failure);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader
        icon="pi pi-file-check"
        title="firmSettings.consent.title"
      />
    </template>

    <p class="text-body text-sm">{{ $t('firmSettings.consent.lede') }}</p>

    <Skeleton
      v-if="isLoading"
      height="12rem"
    />

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('firmSettings.consent.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('firmSettings.retry')"
        @click="load"
      />
    </div>

    <template v-else>
      <form
        novalidate
        class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-4 sm:p-5"
        data-testid="consent-form"
        @submit.prevent="onSubmit"
      >
        <p
          v-if="current"
          class="text-muted text-sm"
          data-testid="consent-current-version"
        >
          {{ $t('firmSettings.consent.current', { version: current.version }) }}
        </p>

        <div class="flex flex-col gap-2">
          <label
            for="consent-text"
            :class="['text-muted mb-2 block text-sm', { 'text-danger-700': error }]"
          >
            {{ $t('firmSettings.consent.label') }}
          </label>

          <Textarea
            v-model="text"
            autoResize
            id="consent-text"
            class="w-full"
            data-testid="consent-text"
            rows="4"
            :invalid="!!error"
            :maxlength="FIRM_CONSENT_MAX_LENGTH"
          />

          <p class="text-muted flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs">
            <span>{{ $t('firmSettings.consent.hint', { placeholder: FIRM_CONSENT_PLACEHOLDER }) }}</span>

            <span class="tabular-nums">{{ counter }}</span>
          </p>

          <AFormError
            data-testid="consent-error"
            :error
          />
        </div>

        <section
          class="bg-panel-muted flex flex-col gap-2 rounded-lg p-4"
          :aria-label="$t('firmSettings.consent.previewTitle')"
        >
          <span class="text-muted text-sm">{{ $t('firmSettings.consent.previewTitle') }}</span>

          <p
            class="text-body text-sm break-words"
            data-testid="consent-preview"
          >
            {{ preview }}
          </p>
        </section>

        <div class="flex justify-end">
          <Button
            data-testid="consent-save"
            type="submit"
            :label="$t('firmSettings.consent.save')"
            :loading="isSaving"
          />
        </div>
      </form>

      <section class="flex min-w-0 flex-col gap-3">
        <h2 class="text-heading text-base font-semibold">{{ $t('firmSettings.consent.history') }}</h2>

        <ol class="bg-panel shadow-lift divide-line flex min-w-0 flex-col divide-y rounded-lg">
          <li
            v-for="version in versions"
            class="flex min-w-0 flex-col gap-2 p-4 sm:p-5"
            data-testid="consent-version-row"
            :key="version.version"
          >
            <div class="text-muted flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
              <span
                class="bg-primary-50 text-primary-950 rounded-sm px-2 py-0.5 font-semibold tabular-nums"
                data-testid="consent-version-number"
              >
                {{ $t('firmSettings.consent.version', { version: version.version }) }}
              </span>

              <span class="tabular-nums">{{ dateOf(version) }}</span>

              <span data-testid="consent-version-author">{{ authorOf(version) }}</span>
            </div>

            <p class="text-body text-sm break-words">{{ version.text }}</p>
          </li>
        </ol>
      </section>
    </template>
  </TheLayout>
</template>
