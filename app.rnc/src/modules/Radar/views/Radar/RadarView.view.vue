<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { RadarBatchResult, RadarPartnerOption, RadarSummary, RadarTypeRow } from '../../interfaces';
import { useFiscalFormat } from '@FiscalModule';
import { RADAR_KIND } from '../../enums';
import { GetRadarService } from '../../services';

/*
 * "Radar da carteira" (rnc-2 R24–R27, the demo's `radarView`): the Reforma's rules engine over every
 * client Franco already has. Four KPIs — clients analysed, with at least one opportunity, the
 * opportunities' total as a range (an estimate; risks never add money) and the risk types found —,
 * then the opportunity rows and, apart, the risk rows. Each row sends its batch to a covering
 * partner (`MRadarTypeRow`); after a batch the Radar is read again, so every count (the next batch,
 * the already sent) is the API's, never this screen's arithmetic (as the ledger, W41).
 */
const TOAST_LIFE_MS = 4000;

const { formatCount, formatRange, formatShortRange } = useFiscalFormat();
const { t } = useI18n();
const toast = useToast();

const loadFailed = ref(false);
const loading = ref(false);
const summary = ref<RadarSummary | null>(null);

const opportunities = computed(
  () => summary.value?.types.filter((type) => type.kind === RADAR_KIND.OPORTUNIDADE) ?? []
);
const risks = computed(() => summary.value?.types.filter((type) => type.kind === RADAR_KIND.RISCO) ?? []);

onMounted(load);

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;

  GetRadarService()
    .then((response) => (summary.value = response.data))
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

// EVENTS
function onSent(type: RadarTypeRow, partner: RadarPartnerOption, result: RadarBatchResult) {
  const hasSent = result.sent > 0;

  toast.add({
    life: TOAST_LIFE_MS,
    severity: hasSent ? 'success' : 'info',
    summary: hasSent
      ? t('radar.sentToast', { count: result.sent, partner: partner.name }, result.sent)
      : t('radar.nothingToast', { partner: partner.name, title: type.title }),
  });
  load();
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="radar.title" />
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-3 rounded-lg p-5">
      <div class="flex flex-col gap-1.5">
        <h2 class="text-heading text-lg font-semibold">{{ $t('radar.heading') }}</h2>

        <p class="text-muted text-sm">{{ $t('radar.lede') }}</p>
      </div>

      <p class="bg-info-50 text-info-800 flex items-start gap-2 rounded-md px-3 py-2 text-sm">
        <i
          aria-hidden="true"
          class="pi pi-info-circle mt-0.5"
        />

        {{ $t('radar.note') }}
      </p>
    </section>

    <div
      v-if="loading && !summary"
      class="flex flex-col gap-3"
    >
      <Skeleton height="7rem" />

      <Skeleton height="24rem" />
    </div>

    <div
      v-else-if="loadFailed && !summary"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('radar.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('radar.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="summary">
      <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
        :aria-label="$t('radar.kpis.label')"
      >
        <div class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4">
          <span class="text-muted text-sm">{{ $t('radar.kpis.clients') }}</span>

          <strong
            v-count-up
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="radar-clients"
            :data-value="summary.clients"
          >
            {{ $t('radar.kpis.clientsValue', { count: formatCount(summary.clients) }, summary.clients) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 1 }"
        >
          <span class="text-muted text-sm">{{ $t('radar.kpis.withOpportunity') }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="radar-with-opportunity"
          >
            {{ formatCount(summary.with_opportunity) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 2 }"
        >
          <span class="text-muted flex flex-wrap items-center gap-2 text-sm">
            {{ $t('radar.kpis.total') }}

            <span class="bg-panel-muted rounded-sm px-1.5 py-0.5 text-xs font-medium">{{ $t('fiscal.estimate') }}</span>
          </span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="radar-total"
            :data-high="summary.total_cents[1]"
            :data-low="summary.total_cents[0]"
            :title="formatRange(summary.total_cents)"
          >
            {{ formatShortRange(summary.total_cents) }}
          </strong>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          :style="{ '--reveal-index': 3 }"
        >
          <span class="text-muted text-sm">{{ $t('radar.kpis.riskTypes') }}</span>

          <strong
            class="text-heading text-2xl font-semibold tabular-nums"
            data-testid="radar-risk-types"
          >
            {{ formatCount(summary.risk_types) }}
          </strong>
        </div>
      </section>

      <section
        :aria-label="$t('radar.opportunities.title')"
        :class="[
          'bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-4 rounded-lg p-5',
          { 'opacity-60 transition-opacity': loading },
        ]"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-heading text-base font-semibold">{{ $t('radar.opportunities.title') }}</h2>

          <p class="text-muted text-sm">{{ $t('radar.opportunities.lede') }}</p>
        </div>

        <p
          v-if="opportunities.length === 0"
          class="text-muted py-4 text-center text-sm"
          data-testid="radar-empty"
        >
          {{ $t('radar.opportunities.empty') }}
        </p>

        <ul
          v-else
          class="flex min-w-0 flex-col"
          data-testid="radar-opportunities"
        >
          <MRadarTypeRow
            v-for="type in opportunities"
            :key="type.rule"
            :type
            @onSent="onSent"
          />
        </ul>
      </section>

      <section
        v-if="risks.length"
        :aria-label="$t('radar.risks.title')"
        :class="[
          'bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-4 rounded-lg p-5',
          { 'opacity-60 transition-opacity': loading },
        ]"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-heading text-base font-semibold">{{ $t('radar.risks.title') }}</h2>

          <p class="text-muted text-sm">{{ $t('radar.risks.lede') }}</p>
        </div>

        <ul
          class="flex min-w-0 flex-col"
          data-testid="radar-risks"
        >
          <MRadarTypeRow
            v-for="type in risks"
            :key="type.rule"
            :type
            @onSent="onSent"
          />
        </ul>
      </section>
    </template>
  </TheLayout>
</template>
