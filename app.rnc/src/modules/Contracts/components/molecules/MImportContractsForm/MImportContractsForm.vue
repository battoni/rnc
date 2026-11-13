<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ContractImportFailure, ContractImportLineError, ContractImportResult } from '../../../interfaces';
import { CONTRACT_IMPORT_ACCEPT, CONTRACT_IMPORT_COLUMNS, CONTRACT_TEMPLATE_FILENAME } from '../../../constants';
import { GetContractsTemplateService, ImportContractsService } from '../../../services';

/*
 * The contracts CSV (rnc-3 R18), inside the view's `MMainDialog`: the columns and the values the API
 * accepts, "Baixar modelo", the file and "Importar". UNLIKE the clients CSV, lines are independent:
 * the API stores the good ones and answers the bad ones by line in a 200 — so the form says how many
 * went in and names each line left out, translated from its code, and stays open for the next file.
 * A file refused whole (422 `import_*`) says why. Every answer is handed to the view (`onImported`),
 * which reads the alert again and closes the dialog when nothing was left out.
 */
interface ImportError {
  response?: { data?: ContractImportFailure; status?: number };
}

const emit = defineEmits<{
  onClose: [];
  onImported: [result: ContractImportResult];
}>();

const { t } = useI18n();

const error = ref<string | null>(null);
const file = ref<File | null>(null);
const isDownloading = ref(false);
const isSending = ref(false);
const result = ref<ContractImportResult | null>(null);

const lineErrors = computed<ContractImportLineError[]>(() => result.value?.errors ?? []);
const hiddenErrors = computed(() => Math.max(0, (result.value?.total_errors ?? 0) - lineErrors.value.length));

// HELPERS
function describeLine(lineError: ContractImportLineError): string {
  const reason = t(`contracts.import.lineCodes.${lineError.code}`);

  return lineError.field ? `${t(`contracts.import.fields.${lineError.field}`)}: ${reason}` : reason;
}

function describeFailure(failure: ContractImportFailure | undefined): string {
  const code = failure?.code;

  if (code === 'import_header')
    return t('contracts.import.codes.import_header', { missing: (failure?.missing ?? []).join(', ') });

  if (code === 'import_too_many') return t('contracts.import.codes.import_too_many', { limit: failure?.limit ?? 0 });

  if (code === 'import_encoding' || code === 'import_empty') return t(`contracts.import.codes.${code}`);

  return t('contracts.import.codes.unexpected');
}

function saveFile(content: string) {
  const url = URL.createObjectURL(new Blob([content], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');

  link.href = url;
  link.download = CONTRACT_TEMPLATE_FILENAME;
  link.click();
  URL.revokeObjectURL(url);
}

// EVENTS
function onFile(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
  error.value = null;
  result.value = null;
}

function onTemplate() {
  isDownloading.value = true;

  GetContractsTemplateService()
    .then(saveFile)
    .catch(() => (error.value = t('contracts.import.templateFailed')))
    .finally(() => (isDownloading.value = false));
}

function onSubmit() {
  if (!file.value) {
    error.value = t('contracts.import.fileRequired');
    return;
  }

  isSending.value = true;
  error.value = null;
  result.value = null;

  ImportContractsService(file.value)
    .then((response) => {
      result.value = response.data.total_errors > 0 ? response.data : null;
      emit('onImported', response.data);
    })
    .catch((failure: ImportError) => (error.value = describeFailure(failure.response?.data)))
    .finally(() => (isSending.value = false));
}
</script>

<template>
  <form
    novalidate
    id="import-contracts-form"
    class="flex flex-col gap-5"
    data-testid="contracts-import-form"
    @submit.prevent="onSubmit"
  >
    <p class="text-body text-sm">{{ $t('contracts.import.lede') }}</p>

    <div class="bg-panel-muted flex flex-col gap-3 rounded-md p-4">
      <span class="text-heading text-sm font-semibold">{{ $t('contracts.import.columns') }}</span>

      <code
        class="text-body text-xs break-all"
        data-testid="contracts-import-columns"
        >{{ CONTRACT_IMPORT_COLUMNS.join(';') }}</code
      >

      <p class="text-muted text-xs">{{ $t('contracts.import.accepted') }}</p>

      <Button
        class="w-full sm:w-fit"
        data-testid="contracts-import-template"
        icon="pi pi-download"
        severity="secondary"
        size="small"
        type="button"
        :label="$t('contracts.import.template')"
        :loading="isDownloading"
        @click="onTemplate"
      />
    </div>

    <div class="flex flex-col gap-2">
      <label
        class="text-muted mb-2 block text-sm"
        for="contracts-import-file"
      >
        {{ $t('contracts.import.file') }}
      </label>

      <input
        id="contracts-import-file"
        class="text-body file:bg-primary-500 file:text-primary-contrast file:touch-target w-full text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:px-4 file:py-2 file:text-sm file:font-semibold"
        data-testid="contracts-import-file"
        type="file"
        :accept="CONTRACT_IMPORT_ACCEPT"
        @change="onFile"
      />
    </div>

    <p
      v-if="error"
      class="bg-danger-50 text-danger-800 rounded-md p-4 text-sm font-medium"
      data-testid="contracts-import-error"
      role="alert"
    >
      {{ error }}
    </p>

    <div
      v-if="result"
      class="bg-warn-50 flex flex-col gap-3 rounded-md p-4"
      role="status"
    >
      <p
        class="text-warn-900 text-sm font-medium"
        data-testid="contracts-import-partial"
      >
        {{
          $t('contracts.import.partial', {
            created: result.created,
            stored: result.created + result.updated,
            updated: result.updated,
          })
        }}
      </p>

      <ul class="flex max-h-60 flex-col gap-1.5 overflow-y-auto">
        <li
          v-for="(lineError, index) in lineErrors"
          class="text-warn-900 text-sm"
          data-testid="contracts-import-line-error"
          :key="index"
          :data-line="lineError.line"
        >
          <strong class="font-semibold tabular-nums">{{
            $t('contracts.import.line', { line: lineError.line })
          }}</strong>
          · {{ describeLine(lineError) }}
        </li>
      </ul>

      <p
        v-if="hiddenErrors > 0"
        class="text-warn-900 text-xs"
        data-testid="contracts-import-more"
      >
        {{ $t('contracts.import.more', { count: hiddenErrors }, hiddenErrors) }}
      </p>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <Button
        severity="secondary"
        type="button"
        :label="result ? $t('contracts.import.close') : $t('contracts.import.cancel')"
        @click="emit('onClose')"
      />

      <Button
        icon="pi pi-upload"
        type="submit"
        :label="$t('contracts.import.submit')"
        :loading="isSending"
      />
    </div>
  </form>
</template>
