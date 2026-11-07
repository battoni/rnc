<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Funnel } from '../../interfaces';
import { METRICS_DEFAULT_WINDOW, METRICS_WINDOWS } from '../../constants';
import { FUNNEL_STEP } from '../../enums';
import { GetFunnelService } from '../../services';

/*
 * Reinaldo's funnel (rnc-1 R24, the demo's `metricsView()`): where each visit comes from, where the
 * business owner gives up and which sector page sells — the seven steps with the drop-off, a table
 * by source and one by sector page, over the last 7 or 30 days. Each step counts only the visitors
 * who passed the one before (api `FunnelService`). Reinaldo and the operator only (`metrics.read`).
 */
const { locale, t } = useI18n();

const days = ref<number>(METRICS_DEFAULT_WINDOW);
const funnel = ref<Funnel | null>(null);
const loading = ref(false);
const loadFailed = ref(false);

const counts = computed<Record<string, number>>(() =>
  Object.fromEntries((funnel.value?.steps ?? []).map((step) => [step.key, step.count]))
);

const kpis = computed(() => {
  const visits = counts.value[FUNNEL_STEP.VISITAS] ?? 0;

  return [
    { icon: 'pi pi-eye', key: 'visits', value: formatCount(visits) },
    { icon: 'pi pi-id-card', key: 'contacts', value: formatCount(counts.value[FUNNEL_STEP.CONTATO] ?? 0) },
    { icon: 'pi pi-calendar-plus', key: 'bookings', value: formatCount(counts.value[FUNNEL_STEP.AGENDOU] ?? 0) },
    { icon: 'pi pi-star', key: 'conversion', value: formatShare(counts.value[FUNNEL_STEP.CLIENTE] ?? 0, visits) },
  ];
});

const sourceRows = computed(() =>
  (funnel.value?.by_source ?? []).map((row) => ({
    cells: [row.visitas, row.cta, row.contato, row.agendou, row.cliente]
      .map(formatCount)
      .concat(formatShare(row.cliente, row.visitas)),
    key: row.source,
    label: t(`metrics.sources.${row.source}`),
  }))
);

const sectorRows = computed(() =>
  (funnel.value?.by_sector ?? []).map((row) => ({
    cells: [formatCount(row.visitas), formatCount(row.cta), formatShare(row.cta, row.visitas)].concat(
      [row.contato, row.agendou].map(formatCount)
    ),
    key: row.slug,
    label: row.label,
  }))
);

const sourceHeaders = computed(() =>
  ['source', 'visitas', 'cta', 'contato', 'agendou', 'cliente', 'conversion'].map((key) => t(`metrics.columns.${key}`))
);

const sectorHeaders = computed(() =>
  ['sector', 'visitas', 'cta', 'ctaRate', 'contato', 'agendou'].map((key) => t(`metrics.columns.${key}`))
);

onMounted(load);

// HELPERS
function formatCount(value: number): string {
  return new Intl.NumberFormat(locale.value).format(value);
}

function formatShare(part: number, whole: number): string {
  const share = whole > 0 ? (part / whole) * 100 : 0;

  return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1, minimumFractionDigits: 1 }).format(share)}%`;
}

function formatDay(at: string): string {
  return new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    month: '2-digit',
    timeZone: 'America/Sao_Paulo',
  }).format(new Date(at));
}

// EVENTS
function load() {
  loading.value = true;
  loadFailed.value = false;

  GetFunnelService(days.value)
    .then((response) => (funnel.value = response.data))
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

function onPeriod(value: number) {
  if (value === days.value) return;

  days.value = value;
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="metrics.title" />
    </template>

    <section
      class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5 lg:flex-row lg:items-center lg:justify-between"
      data-testid="metrics"
      :data-days="days"
    >
      <div class="flex min-w-0 flex-col gap-1.5">
        <h2 class="text-heading text-lg font-semibold">{{ $t('metrics.heading', { days }) }}</h2>

        <p class="text-muted text-sm">{{ $t('metrics.lede') }}</p>
      </div>

      <div class="flex shrink-0 flex-col gap-1.5 lg:items-end">
        <div
          class="bg-panel-muted flex w-fit gap-1 rounded-md p-1"
          role="group"
          :aria-label="$t('metrics.period')"
        >
          <Button
            v-for="option in METRICS_WINDOWS"
            :key="option"
            size="small"
            :aria-pressed="option === days ? 'true' : 'false'"
            :data-testid="`metrics-days-${option}`"
            :label="$t('metrics.days', { days: option })"
            :severity="option === days ? undefined : 'secondary'"
            :text="option !== days"
            @click="onPeriod(option)"
          />
        </div>

        <span
          v-if="funnel"
          class="text-subtle text-xs tabular-nums"
          data-testid="metrics-range"
        >
          {{ $t('metrics.range', { from: formatDay(funnel.from), to: formatDay(funnel.to) }) }}
        </span>
      </div>
    </section>

    <div
      v-if="loading && !funnel"
      class="flex flex-col gap-3"
    >
      <Skeleton height="6rem" />

      <Skeleton height="20rem" />
    </div>

    <div
      v-else-if="loadFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('metrics.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('metrics.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="funnel">
      <ul :class="['grid grid-cols-2 gap-3 lg:grid-cols-4', { 'opacity-60 transition-opacity': loading }]">
        <li
          v-for="kpi in kpis"
          class="bg-panel shadow-lift flex min-w-0 flex-col gap-2 rounded-lg p-4"
          data-testid="metrics-kpi"
          :key="kpi.key"
          :data-key="kpi.key"
        >
          <span class="text-muted flex items-center gap-2 text-xs font-medium">
            <i
              aria-hidden="true"
              :class="[kpi.icon, 'text-primary-500 text-sm']"
            />

            {{ $t(`metrics.kpis.${kpi.key}`) }}
          </span>

          <strong class="text-heading text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{
            kpi.value
          }}</strong>
        </li>
      </ul>

      <section
        :class="[
          'bg-panel shadow-lift flex flex-col gap-5 rounded-lg p-5 sm:p-6',
          { 'opacity-60 transition-opacity': loading },
        ]"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-heading text-base font-semibold">{{ $t('metrics.funnelTitle') }}</h2>

          <p class="text-muted text-sm">{{ $t('metrics.funnelLede') }}</p>
        </div>

        <MFunnelChart :steps="funnel.steps" />

        <p class="text-subtle border-line border-t pt-4 text-xs">{{ $t('metrics.clientNote') }}</p>
      </section>

      <div :class="['grid min-w-0 grid-cols-1 gap-4 2xl:grid-cols-2', { 'opacity-60 transition-opacity': loading }]">
        <section class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-5">
          <div class="flex flex-col gap-1">
            <h2 class="text-heading text-base font-semibold">{{ $t('metrics.bySource.title') }}</h2>

            <p class="text-muted text-sm">{{ $t('metrics.bySource.lede') }}</p>
          </div>

          <MFunnelTable
            testId="source-row"
            :caption="$t('metrics.bySource.title')"
            :headers="sourceHeaders"
            :rows="sourceRows"
          />
        </section>

        <section class="bg-panel shadow-lift flex min-w-0 flex-col gap-4 rounded-lg p-5">
          <div class="flex flex-col gap-1">
            <h2 class="text-heading text-base font-semibold">{{ $t('metrics.bySector.title') }}</h2>

            <p class="text-muted text-sm">{{ $t('metrics.bySector.lede') }}</p>
          </div>

          <MFunnelTable
            testId="sector-row"
            :caption="$t('metrics.bySector.title')"
            :headers="sectorHeaders"
            :rows="sectorRows"
          />
        </section>
      </div>
    </template>
  </TheLayout>
</template>
