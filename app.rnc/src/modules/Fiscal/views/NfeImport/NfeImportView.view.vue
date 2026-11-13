<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { NfeImport } from '../../interfaces';
import { usePaginatedList } from '@Composables';
import { IMPORTS_PER_PAGE } from '../../constants';
import { GetNfeImportsService } from '../../services';

/*
 * Reinaldo's "Notas fiscais" (rnc-2 R15, R17, D5): the upload of one XML or a ZIP of them, the batch
 * it made — files read, documents stored, every refusal by file and its reason — and the firm's
 * batches, newest first, the folder command's (`nfe:import`) among them. The new batch goes on top
 * of the list from the API's answer, never a refetch (celer-07). `fiscal.import` only (D14).
 */
const TOAST_LIFE_MS = 3500;

const { t } = useI18n();
const { items, load, loadFailed, loading, onPage, page, perPage, prependItem, total } = usePaginatedList<NfeImport>(
  (params) => GetNfeImportsService(params),
  { perPage: IMPORTS_PER_PAGE }
);
const toast = useToast();

const latest = ref<NfeImport | null>(null);

onMounted(load);

// EVENTS
function onImported(batch: NfeImport) {
  latest.value = batch;
  prependItem(batch);
  toast.add({
    life: TOAST_LIFE_MS,
    severity: batch.refused.length > 0 ? 'warn' : 'success',
    summary: t('fiscal.imports.success', { files: batch.files, stored: batch.stored }),
  });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="fiscal.imports.title" />
    </template>

    <section
      class="bg-panel shadow-lift motion-enter grid min-w-0 grid-cols-1 gap-6 rounded-lg p-5 sm:p-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]"
    >
      <div class="flex min-w-0 flex-col gap-1.5">
        <h2 class="text-heading text-lg font-semibold">{{ $t('fiscal.imports.heading') }}</h2>

        <p class="text-muted text-sm">{{ $t('fiscal.imports.lede') }}</p>
      </div>

      <MNfeImportForm @onImported="onImported" />
    </section>

    <section
      v-if="latest"
      class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-3 rounded-lg p-5"
      data-testid="nfe-result"
    >
      <h2 class="text-heading text-base font-semibold">{{ $t('fiscal.imports.result') }}</h2>

      <MNfeBatch
        isExpanded
        :key="latest.id"
        :batch="latest"
      />
    </section>

    <section
      class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-5"
      data-testid="nfe-batches"
    >
      <h2 class="text-heading text-base font-semibold">{{ $t('fiscal.imports.batches.title') }}</h2>

      <div
        v-if="loading && items.length === 0"
        class="flex flex-col gap-3"
      >
        <Skeleton height="6rem" />

        <Skeleton height="6rem" />
      </div>

      <div
        v-else-if="loadFailed"
        class="flex flex-col items-center gap-4 p-6 text-center"
        role="alert"
      >
        <p class="text-body">{{ $t('fiscal.imports.batches.loadFailed') }}</p>

        <Button
          icon="pi pi-refresh"
          severity="secondary"
          :label="$t('fiscal.retry')"
          @click="load"
        />
      </div>

      <p
        v-else-if="items.length === 0"
        class="text-muted py-4 text-center text-sm"
      >
        {{ $t('fiscal.imports.batches.empty') }}
      </p>

      <ol
        v-else
        :class="['flex flex-col gap-3', { 'opacity-60 transition-opacity': loading }]"
      >
        <li
          v-for="batch in items"
          :key="batch.id"
        >
          <MNfeBatch :batch />
        </li>
      </ol>

      <OPaginator
        v-if="total > perPage"
        :page
        :perPage
        :total
        @onPage="onPage"
      />
    </section>
  </TheLayout>
</template>
