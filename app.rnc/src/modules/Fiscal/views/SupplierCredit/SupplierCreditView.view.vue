<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { SupplierCreditClient, SupplierCreditList } from '../../interfaces';
import type { CLIENT_SECTOR } from '@RepricingModule';
import { useFiscalFormat } from '../../composables';
import { FISCAL_SECTORS } from '../../constants';
import { GetSupplierCreditService } from '../../services';

/*
 * "Crédito de fornecedores" (rnc-2 R21–R22, the demo's `suppliersView`): from 2027 a purchase from a
 * Simples supplier yields little IBS/CBS credit — how much each Presumido/Real client loses a year,
 * as a range labelled an estimate. The KPIs over the listed clients follow the sector filter
 * (W76), which asks the API again; the rows come sorted by the top of the range. A proposed review
 * replaces its row and bumps the KPI from the API's line — no refetch (celer-07).
 */
const TOAST_LIFE_MS = 3500;

const { formatCount, formatPercent, formatRange, formatShortRange } = useFiscalFormat();
const { t } = useI18n();
const toast = useToast();

const list = ref<SupplierCreditList | null>(null);
const loadFailed = ref(false);
const loading = ref(false);
const sector = ref<CLIENT_SECTOR | null>(null);

const sectorOptions = computed(() => FISCAL_SECTORS.map((value) => ({ label: t(`fiscal.sectors.${value}`), value })));
const top = computed(() => list.value?.clients[0]?.lost_per_year_cents[1] ?? 0);

onMounted(load);

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;

  GetSupplierCreditService(sector.value)
    .then((response) => (list.value = response.data))
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

// EVENTS
function onSector(value: CLIENT_SECTOR | null) {
  sector.value = value ?? null;
  load();
}

function onProposed(client: SupplierCreditClient) {
  if (!list.value) return;

  const wasProposed = client.proposed;

  list.value = {
    clients: list.value.clients.map((row) => (row.id === client.id ? { ...row, proposed: true } : row)),
    summary: { ...list.value.summary, proposed: list.value.summary.proposed + (wasProposed ? 0 : 1) },
  };
  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('fiscal.suppliers.proposedToast', { name: client.name }),
  });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="fiscal.suppliers.title" />
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-3 rounded-lg p-5">
      <div class="flex flex-col gap-1.5">
        <h2 class="text-heading text-lg font-semibold">{{ $t('fiscal.suppliers.heading') }}</h2>

        <p class="text-muted text-sm">{{ $t('fiscal.suppliers.lede') }}</p>
      </div>

      <p class="bg-info-50 text-info-800 flex items-start gap-2 rounded-md px-3 py-2 text-sm">
        <i
          aria-hidden="true"
          class="pi pi-info-circle mt-0.5"
        />

        {{ $t('fiscal.suppliers.note') }}
      </p>
    </section>

    <div
      v-if="loading && !list"
      class="flex flex-col gap-3"
    >
      <Skeleton height="7rem" />

      <Skeleton height="24rem" />
    </div>

    <div
      v-else-if="loadFailed && !list"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('fiscal.suppliers.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('fiscal.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="list">
      <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
        :aria-label="$t('fiscal.suppliers.kpis.label')"
      >
        <div class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4">
          <span class="text-muted flex flex-wrap items-center gap-2 text-sm">
            {{ $t('fiscal.suppliers.kpis.total') }}

            <span class="bg-panel-muted rounded-sm px-1.5 py-0.5 text-xs font-medium">{{ $t('fiscal.estimate') }}</span>
          </span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="supplier-total"
            :data-high="list.summary.lost_per_year_cents[1]"
            :data-low="list.summary.lost_per_year_cents[0]"
            :title="formatRange(list.summary.lost_per_year_cents)"
          >
            {{ formatShortRange(list.summary.lost_per_year_cents) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 1 }"
        >
          <span class="text-muted text-sm">{{ $t('fiscal.suppliers.kpis.buying') }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="supplier-buying"
          >
            {{
              $t('fiscal.suppliers.kpis.buyingValue', {
                count: formatCount(list.summary.buying_from_simples),
                total: formatCount(list.summary.clients),
              })
            }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 2 }"
        >
          <span class="text-muted text-sm">{{ $t('fiscal.suppliers.kpis.mean') }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="supplier-mean-share"
          >
            {{ formatPercent(list.summary.mean_simples_share) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 3 }"
        >
          <span class="text-muted text-sm">{{ $t('fiscal.suppliers.kpis.proposed') }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="supplier-proposed-count"
          >
            {{ formatCount(list.summary.proposed) }}
          </strong>
        </div>
      </section>

      <section
        class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5 lg:flex-row lg:items-end lg:justify-between"
      >
        <div class="flex w-full flex-col gap-2 lg:w-72">
          <label
            class="text-muted mb-2 block text-sm"
            for="supplier-sector"
          >
            {{ $t('fiscal.suppliers.filters.sector') }}
          </label>

          <Select
            showClear
            class="w-full"
            inputId="supplier-sector"
            optionLabel="label"
            optionValue="value"
            :modelValue="sector"
            :options="sectorOptions"
            :placeholder="$t('fiscal.suppliers.filters.allSectors')"
            @update:modelValue="onSector"
          />
        </div>

        <ul class="flex flex-wrap gap-x-5 gap-y-2">
          <li class="text-body flex items-center gap-2 text-sm">
            <i
              aria-hidden="true"
              class="bg-warn-200 size-2.5 shrink-0 rounded-sm"
            />

            {{ $t('fiscal.suppliers.legend.simples') }}
          </li>

          <li class="text-body flex items-center gap-2 text-sm">
            <i
              aria-hidden="true"
              class="bg-primary-100 size-2.5 shrink-0 rounded-sm"
            />

            {{ $t('fiscal.suppliers.legend.normal') }}
          </li>
        </ul>
      </section>

      <section
        :class="[
          'bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-5',
          { 'opacity-60 transition-opacity': loading },
        ]"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-heading text-base font-semibold">{{ $t('fiscal.suppliers.list.title') }}</h2>

          <p class="text-muted text-sm">{{ $t('fiscal.suppliers.list.lede') }}</p>
        </div>

        <p
          v-if="list.clients.length === 0"
          class="text-muted py-4 text-center text-sm"
          data-testid="supplier-empty"
        >
          {{ $t('fiscal.suppliers.list.empty') }}
        </p>

        <ul
          v-else
          class="flex min-w-0 flex-col"
        >
          <MSupplierCreditRow
            v-for="client in list.clients"
            :key="client.id"
            :client
            :top
            @onProposed="onProposed"
          />
        </ul>
      </section>
    </template>
  </TheLayout>
</template>
