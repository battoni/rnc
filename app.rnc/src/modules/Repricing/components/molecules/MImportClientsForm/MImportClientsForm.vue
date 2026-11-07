<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ClientImportFailure, ClientImportLineError, ClientImportResult } from '../../../interfaces';
import { IMPORT_ACCEPT, IMPORT_COLUMNS, IMPORT_TEMPLATE_FILENAME } from '../../../constants';
import { GetClientsTemplateService, ImportClientsService } from '../../../services';

/*
 * The portfolio's CSV import (rnc-1 R25, A13), inside the view's `MMainDialog`: the columns and the
 * values the API accepts, "Baixar modelo", the file and "Importar". The API refuses a file whole and
 * says why by a stable `code` (`ClientImportException`); an `import_invalid` lists every bad value
 * by its line in the file, translated here from its code — nothing was imported, so one round of
 * fixes and a new upload is the whole loop. A good file answers the counts to the view.
 */
interface ImportError {
  response?: { data?: ClientImportFailure; status?: number };
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [result: ClientImportResult];
}>();

const { t } = useI18n();

const error = ref<string | null>(null);
const file = ref<File | null>(null);
const isDownloading = ref(false);
const isSending = ref(false);
const lineErrors = ref<ClientImportLineError[]>([]);
const totalErrors = ref(0);

const hiddenErrors = computed(() => Math.max(0, totalErrors.value - lineErrors.value.length));

// HELPERS
function describeLine(lineError: ClientImportLineError): string {
  const field = lineError.field ? t(`repricing.import.fields.${lineError.field}`) : '';

  return t(`repricing.import.lineCodes.${lineError.code}`, { field });
}

function describeFailure(failure: ClientImportFailure | undefined): string {
  const code = failure?.code;

  if (code === 'import_header')
    return t('repricing.import.codes.import_header', { missing: (failure?.missing ?? []).join(', ') });

  if (code === 'import_too_many') return t('repricing.import.codes.import_too_many', { limit: failure?.limit ?? 0 });

  if (code === 'import_encoding' || code === 'import_empty' || code === 'import_invalid')
    return t(`repricing.import.codes.${code}`);

  return t('repricing.import.codes.unexpected');
}

function saveFile(content: string) {
  const url = URL.createObjectURL(new Blob([content], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');

  link.href = url;
  link.download = IMPORT_TEMPLATE_FILENAME;
  link.click();
  URL.revokeObjectURL(url);
}

// EVENTS
function onFile(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
  error.value = null;
  lineErrors.value = [];
  totalErrors.value = 0;
}

function onTemplate() {
  isDownloading.value = true;

  GetClientsTemplateService()
    .then(saveFile)
    .catch(() => (error.value = t('repricing.import.templateFailed')))
    .finally(() => (isDownloading.value = false));
}

function onSubmit() {
  if (!file.value) {
    error.value = t('repricing.import.fileRequired');
    return;
  }

  isSending.value = true;
  error.value = null;
  lineErrors.value = [];

  ImportClientsService(file.value)
    .then((response) => emit('onSuccess', response.data))
    .catch(onFailed)
    .finally(() => (isSending.value = false));
}

function onFailed(failure: ImportError) {
  const body = failure.response?.data;

  error.value = describeFailure(body);
  lineErrors.value = body?.errors ?? [];
  totalErrors.value = body?.total_errors ?? lineErrors.value.length;
}
</script>

<template>
  <form
    novalidate
    id="import-clients-form"
    class="flex flex-col gap-5"
    data-testid="import-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body text-sm">{{ $t('repricing.import.lede') }}</p>

    <div class="bg-panel-muted flex flex-col gap-3 rounded-md p-4">
      <span class="text-heading text-sm font-semibold">{{ $t('repricing.import.columns') }}</span>

      <code class="text-body text-xs break-all">{{ IMPORT_COLUMNS.join(';') }}</code>

      <p class="text-muted text-xs">{{ $t('repricing.import.accepted') }}</p>

      <Button
        class="w-full sm:w-fit"
        icon="pi pi-download"
        severity="secondary"
        size="small"
        type="button"
        :label="$t('repricing.import.template')"
        :loading="isDownloading"
        @click="onTemplate"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="import-file"
      >
        {{ $t('repricing.import.file') }}
      </label>

      <input
        id="import-file"
        class="text-body file:bg-primary-500 file:text-primary-contrast file:touch-target w-full text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:px-4 file:py-2 file:text-sm file:font-semibold"
        data-testid="import-file"
        type="file"
        :accept="IMPORT_ACCEPT"
        @change="onFile"
      />
    </div>

    <div
      v-if="error"
      class="bg-danger-50 flex flex-col gap-3 rounded-md p-4"
      role="alert"
    >
      <p
        class="text-danger-700 text-sm font-medium"
        data-testid="import-error"
      >
        {{ error }}
      </p>

      <ul
        v-if="lineErrors.length > 0"
        class="flex max-h-60 flex-col gap-1.5 overflow-y-auto"
      >
        <li
          v-for="(lineError, index) in lineErrors"
          class="text-body text-sm"
          data-testid="import-line-error"
          :key="index"
          :data-line="lineError.line"
        >
          <strong class="font-semibold tabular-nums">{{
            $t('repricing.import.line', { line: lineError.line })
          }}</strong>
          · {{ describeLine(lineError) }}
        </li>
      </ul>

      <p
        v-if="hiddenErrors > 0"
        class="text-muted text-xs"
      >
        {{ $t('repricing.import.more', { count: hiddenErrors }) }}
      </p>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <Button
        severity="secondary"
        type="button"
        :label="$t('repricing.import.cancel')"
        @click="emit('onClose')"
      />

      <Button
        icon="pi pi-upload"
        type="submit"
        :label="$t('repricing.import.submit')"
        :loading="isSending"
      />
    </div>
  </form>
</template>
