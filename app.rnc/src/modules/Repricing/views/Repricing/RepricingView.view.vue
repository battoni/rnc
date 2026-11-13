<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { CLIENT_REGIME, CLIENT_SECTOR } from '../../enums';
import type {
  ClientImportResult,
  MarkRepricingResult,
  RepricingClient,
  RepricingClientDetail,
  RepricingFilters,
  RepricingList,
} from '../../interfaces';
import { formatReal } from '@Helpers';
import { useUserStore } from '@UserModule';
import { DETAIL_BESIDE_QUERY, REPRICING_PERMISSIONS, REPRICING_REGIMES, REPRICING_SECTORS } from '../../constants';
import { GetRepricingClientService, GetRepricingService, MarkRepricingService } from '../../services';

/*
 * Reinaldo's fee repricing (rnc-1 R26, the demo's `repricingView()`): how much the Reforma's extra
 * work is worth a year over the listed clients — the hero total is Σ (suggested − current) × 12 of
 * exactly the rows below, computed by the API —, the spread by band of percent, the sector and
 * regime filters (each asks the API again), the list, one client's items with its letter, and
 * "Marcar em lote" for the selected rows. The CSV import sits in the header for `clients.import`.
 */
const TOAST_LIFE_MS = 3500;

const DETAIL_SCROLL_MARGIN = 16;

const userStore = useUserStore();

const { locale, t } = useI18n();
const toast = useToast();

const detail = ref<RepricingClientDetail | null>(null);
const detailFailed = ref(false);
const detailLoading = ref(false);
const dialogVisible = ref(false);
const filters = ref<RepricingFilters>({ regime: null, sector: null });
const isMarking = ref(false);
const list = ref<RepricingList | null>(null);
const loadFailed = ref(false);
const loading = ref(false);
const openId = ref<number | null>(null);
const selectedIds = ref<number[]>([]);

const canImport = computed(() => userStore.hasPermission(REPRICING_PERMISSIONS.import));

const kpis = computed(() => {
  const summary = list.value?.summary;

  return [
    { key: 'clients', testId: 'repricing-clients-count', value: formatCount(summary?.clients ?? 0) },
    {
      key: 'average',
      testId: 'repricing-average',
      value: summary?.clients ? `${formatDecimal(summary.average_pct)}%` : '—',
    },
    { key: 'marked', testId: 'repricing-marked-count', value: formatCount(summary?.marked ?? 0) },
  ];
});

const regimeOptions = computed(() =>
  REPRICING_REGIMES.map((regime) => ({ label: t(`repricing.regimes.${regime}`), value: regime }))
);

const sectorOptions = computed(() =>
  REPRICING_SECTORS.map((sector) => ({ label: t(`repricing.sectors.${sector}`), value: sector }))
);

onMounted(load);

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;

  GetRepricingService(filters.value)
    .then(onLoaded)
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

function formatCount(value: number): string {
  return new Intl.NumberFormat(locale.value).format(value);
}

function formatDecimal(value: number): string {
  return new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(value);
}

function replaceRows(clients: RepricingClient[]) {
  if (!list.value) return;

  const byId = new Map(clients.map((client) => [client.id, client]));
  const newlyMarked = list.value.clients.filter((client) => byId.has(client.id) && !client.repricing).length;

  list.value = {
    ...list.value,
    clients: list.value.clients.map((client) => byId.get(client.id) ?? client),
    summary: { ...list.value.summary, marked: list.value.summary.marked + newlyMarked },
  };

  const openRow = detail.value ? byId.get(detail.value.id) : undefined;

  if (openRow && detail.value) detail.value = { ...detail.value, repricing: openRow.repricing };
}

/*
 * Scrolls TheLayout's own scroller, never `scrollIntoView`: that also scrolls `#app` and the
 * document, which lifted the whole layout off the top and left a blank screen under it.
 */
function scrollToDetail() {
  const target = document.querySelector('[data-testid=repricing-detail]');
  const scroller = target?.closest('.main-scroll');

  if (!target || !scroller) return;

  const offset = target.getBoundingClientRect().top - scroller.getBoundingClientRect().top - DETAIL_SCROLL_MARGIN;

  scroller.scrollBy?.({ behavior: 'smooth', top: offset });
}

// EVENTS
function onLoaded(response: { data: RepricingList }) {
  list.value = response.data;

  const stillListed = response.data.clients.some((client) => client.id === openId.value);

  if (stillListed) return;

  openId.value = null;
  detail.value = null;
}

function onFilter(key: keyof RepricingFilters, value: CLIENT_REGIME | CLIENT_SECTOR | null) {
  filters.value = { ...filters.value, [key]: value ?? null };
  selectedIds.value = [];
  load();
}

function onToggle(id: number) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((selected) => selected !== id)
    : [...selectedIds.value, id];
}

function onToggleAll(checked: boolean) {
  selectedIds.value = checked ? (list.value?.clients ?? []).map((client) => client.id) : [];
}

function onOpen(id: number) {
  openId.value = id;
  detailLoading.value = true;
  detailFailed.value = false;

  GetRepricingClientService(id)
    .then((response) => (detail.value = response.data))
    .catch(() => (detailFailed.value = true))
    .finally(() => (detailLoading.value = false));

  if (window.matchMedia?.(DETAIL_BESIDE_QUERY).matches) return;

  nextTick(scrollToDetail);
}

function onMark() {
  const payload = { client_ids: [...selectedIds.value] };

  isMarking.value = true;

  MarkRepricingService(payload)
    .then(onMarked)
    .catch(() => toast.add({ life: TOAST_LIFE_MS, severity: 'error', summary: t('repricing.batch.failed') }))
    .finally(() => (isMarking.value = false));
}

function onMarked(response: { data: MarkRepricingResult }) {
  const count = response.data.marked;

  replaceRows(response.data.clients);
  selectedIds.value = [];
  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('repricing.batch.marked', { count }, count) });
}

function onImported(result: ClientImportResult) {
  dialogVisible.value = false;
  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('repricing.import.success', { ...result }) });
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="repricing.title">
        <template
          v-if="canImport && !dialogVisible"
          #actions
        >
          <Button
            data-testid="repricing-import"
            icon="pi pi-upload"
            severity="secondary"
            :label="$t('repricing.import.action')"
            @click="dialogVisible = true"
          />
        </template>
      </ThePageHeader>
    </template>

    <section class="bg-panel shadow-lift flex flex-col gap-1.5 rounded-lg p-5">
      <h2 class="text-heading text-lg font-semibold">{{ $t('repricing.heading') }}</h2>

      <p class="text-muted text-sm">{{ $t('repricing.lede') }}</p>
    </section>

    <div
      v-if="loading && !list"
      class="flex flex-col gap-3"
    >
      <Skeleton height="10rem" />

      <Skeleton height="24rem" />
    </div>

    <div
      v-else-if="loadFailed && !list"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('repricing.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('repricing.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="list">
      <section
        :class="[
          'bg-panel shadow-lift grid min-w-0 grid-cols-1 gap-6 rounded-lg p-5 sm:p-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]',
          { 'opacity-60 transition-opacity': loading },
        ]"
      >
        <div class="flex min-w-0 flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="text-muted text-xs font-semibold tracking-wide uppercase">{{
              $t('repricing.hero.label')
            }}</span>

            <strong
              class="text-heading text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl"
              data-testid="repricing-total"
              :data-value="list.summary.uplift_total_cents"
            >
              {{ formatReal(list.summary.uplift_total_cents) }}
            </strong>

            <p class="text-muted text-sm">
              {{
                $t('repricing.hero.base', {
                  base: formatReal(list.summary.current_yearly_cents),
                  share: `${formatDecimal(list.summary.uplift_share)}%`,
                })
              }}
            </p>
          </div>

          <ul class="grid grid-cols-3 gap-3">
            <li
              v-for="kpi in kpis"
              class="bg-panel-muted flex min-w-0 flex-col gap-1 rounded-md p-3"
              :key="kpi.key"
            >
              <span class="text-muted text-xs font-medium">{{ $t(`repricing.kpis.${kpi.key}`) }}</span>

              <strong
                class="text-heading text-xl font-semibold tabular-nums"
                :data-testid="kpi.testId"
              >
                {{ kpi.value }}
              </strong>
            </li>
          </ul>
        </div>

        <div class="border-line flex min-w-0 flex-col gap-3 border-t pt-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
          <span class="text-muted text-xs font-semibold tracking-wide uppercase">{{
            $t('repricing.spread.title')
          }}</span>

          <MRepricingSpread :bands="list.spread" />
        </div>
      </section>

      <section
        class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5 lg:flex-row lg:items-end lg:justify-between"
      >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:w-[30rem]">
          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="repricing-sector"
            >
              {{ $t('repricing.filters.sector') }}
            </label>

            <Select
              showClear
              class="w-full"
              inputId="repricing-sector"
              optionLabel="label"
              optionValue="value"
              :modelValue="filters.sector"
              :options="sectorOptions"
              :placeholder="$t('repricing.filters.allSectors')"
              @update:modelValue="onFilter('sector', $event)"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="repricing-regime"
            >
              {{ $t('repricing.filters.regime') }}
            </label>

            <Select
              showClear
              class="w-full"
              inputId="repricing-regime"
              optionLabel="label"
              optionValue="value"
              :modelValue="filters.regime"
              :options="regimeOptions"
              :placeholder="$t('repricing.filters.allRegimes')"
              @update:modelValue="onFilter('regime', $event)"
            />
          </div>
        </div>

        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
          <span
            class="text-muted min-w-0 text-sm"
            data-testid="repricing-selected"
          >
            {{
              selectedIds.length > 0
                ? $t('repricing.batch.selected', { count: selectedIds.length }, selectedIds.length)
                : $t('repricing.batch.none')
            }}
          </span>

          <Button
            class="shrink-0 whitespace-nowrap"
            data-testid="repricing-mark"
            icon="pi pi-check-square"
            :disabled="selectedIds.length === 0"
            :label="$t('repricing.batch.mark')"
            :loading="isMarking"
            @click="onMark"
          />
        </div>
      </section>

      <div class="grid min-w-0 grid-cols-1 items-start gap-4 2xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section
          :class="[
            'bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-4 sm:p-5',
            { 'opacity-60 transition-opacity': loading },
          ]"
        >
          <div
            v-if="list.clients.length === 0"
            class="flex flex-col items-center gap-2 p-6 text-center"
            data-testid="repricing-empty"
          >
            <i
              aria-hidden="true"
              class="pi pi-briefcase text-subtle text-2xl"
            />

            <p class="text-heading font-semibold">{{ $t('repricing.table.emptyTitle') }}</p>

            <p class="text-muted text-sm">{{ $t('repricing.table.emptyText') }}</p>
          </div>

          <MRepricingTable
            v-else
            :clients="list.clients"
            :openId
            :selectedIds
            @onOpen="onOpen"
            @onToggle="onToggle"
            @onToggleAll="onToggleAll"
          />
        </section>

        <MRepricingDetail
          class="2xl:sticky 2xl:top-4"
          :client="detail"
          :failed="detailFailed"
          :loading="detailLoading"
        />
      </div>
    </template>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        title="repricing.import.title"
      >
        <MImportClientsForm
          @onClose="dialogVisible = false"
          @onSuccess="onImported"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
