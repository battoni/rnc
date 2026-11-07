<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue';
import type { NfeImport } from '../../../interfaces';
import { IMPORT_ACCEPT, IMPORT_EXTENSIONS, IMPORT_MAX_BYTES } from '../../../constants';
import { ImportNfeService } from '../../../services';

/*
 * The NF-e upload (rnc-2 R15, R17): one `.xml` or `.zip` of up to 20 MB. What the API would refuse
 * whole — no file, another kind, too big — is stopped here, before a request (the API checks again,
 * `StoreNfeImportRequest`). A request it refuses is named by its status: 422 the file, 429 the
 * import throttle (10 a minute), anything else a failure. A batch that ran is the answer even with
 * refusals — a broken file never stops the others —, so it goes to the view and the file is cleared.
 */
type ImportErrorReason = 'failed' | 'invalid' | 'kind' | 'required' | 'size' | 'throttled';

interface ImportError {
  response?: { status?: number };
}

const emit = defineEmits<{
  onImported: [batch: NfeImport];
}>();

const HTTP_UNPROCESSABLE = 422;
const HTTP_TOO_MANY = 429;

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');

const errorReason = ref<ImportErrorReason | null>(null);
const file = ref<File | null>(null);
const isSending = ref(false);

const errorKey = computed(() => (errorReason.value ? `fiscal.imports.form.errors.${errorReason.value}` : null));

// HELPERS
function extensionOf(name: string): string {
  return name.split('.').pop()?.toLowerCase() ?? '';
}

function reasonToRefuse(chosen: File): ImportErrorReason | null {
  const isAccepted = (IMPORT_EXTENSIONS as readonly string[]).includes(extensionOf(chosen.name));

  if (!isAccepted) return 'kind';

  return chosen.size > IMPORT_MAX_BYTES ? 'size' : null;
}

function reasonOf(failure: ImportError): ImportErrorReason {
  const status = failure.response?.status;

  if (status === HTTP_UNPROCESSABLE) return 'invalid';

  return status === HTTP_TOO_MANY ? 'throttled' : 'failed';
}

// EVENTS
function onFile(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
  errorReason.value = null;
}

function onSubmit() {
  const chosen = file.value;

  if (!chosen) {
    errorReason.value = 'required';
    return;
  }

  errorReason.value = reasonToRefuse(chosen);

  if (errorReason.value) return;

  isSending.value = true;

  ImportNfeService(chosen)
    .then((response) => onImported(response.data))
    .catch((failure: ImportError) => (errorReason.value = reasonOf(failure)))
    .finally(() => (isSending.value = false));
}

function onImported(batch: NfeImport) {
  file.value = null;

  if (fileInput.value) fileInput.value.value = '';

  emit('onImported', batch);
}
</script>

<template>
  <form
    novalidate
    class="flex flex-col gap-4"
    data-testid="nfe-import-form"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        for="nfe-file"
        :class="['mb-2 block text-sm', errorReason ? 'text-danger-700' : 'text-muted']"
      >
        {{ $t('fiscal.imports.form.file') }}
      </label>

      <input
        id="nfe-file"
        class="text-body file:bg-primary-500 file:text-primary-contrast file:touch-target w-full min-w-0 text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:px-4 file:py-2 file:text-sm file:font-semibold"
        data-testid="nfe-file"
        ref="fileInput"
        type="file"
        :accept="IMPORT_ACCEPT"
        :aria-describedby="errorReason ? 'nfe-import-error nfe-import-hint' : 'nfe-import-hint'"
        :aria-invalid="errorReason ? 'true' : 'false'"
        @change="onFile"
      />

      <p
        id="nfe-import-hint"
        class="text-muted text-xs"
      >
        {{ $t('fiscal.imports.form.accepted') }}
      </p>
    </div>

    <p
      v-if="errorKey"
      id="nfe-import-error"
      class="bg-danger-50 text-danger-800 motion-enter rounded-md px-3 py-2 text-sm font-medium"
      data-testid="nfe-import-error"
      role="alert"
      :data-reason="errorReason"
    >
      {{ $t(errorKey) }}
    </p>

    <Button
      class="w-full sm:w-fit"
      data-testid="nfe-import-submit"
      icon="pi pi-upload"
      type="submit"
      :label="$t('fiscal.imports.form.submit')"
      :loading="isSending"
    />
  </form>
</template>
