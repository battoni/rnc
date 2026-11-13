<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { ClientEvent, ReadinessDetail, ReadinessSample } from '../../../interfaces';
import { useFiscalFormat } from '../../../composables';

/*
 * One client of the readiness opened (rnc-2 R19, the demo's `nfeDetail`): its profile and emitting
 * system, the window's invoices, the % complete and those at risk, the status, every issue line
 * with how many invoices and up to five sample invoices (the 1115 risk in the danger tone), the
 * client's timeline and "Avisar cliente". The view loads the client and its timeline and passes
 * them down; "Avisar cliente" is the view's dialog.
 */
interface Props {
  client: ReadinessDetail | null;
  events: ClientEvent[];
  eventsFailed: boolean;
  eventsLoading: boolean;
  failed: boolean;
  loading: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
  onNotify: [];
}>();

const { formatCount, formatPercent } = useFiscalFormat();
const { t } = useI18n();

// HELPERS
function sampleOf(sample: ReadinessSample): string {
  const values = { item: sample.item ?? '', number: sample.number, series: sample.series };

  return sample.item === null
    ? t('fiscal.readiness.detail.sampleNoItem', values)
    : t('fiscal.readiness.detail.sample', values);
}
</script>

<template>
  <section
    class="bg-panel shadow-lift flex min-w-0 flex-col gap-5 rounded-lg p-5 sm:p-6"
    data-testid="readiness-detail"
    :aria-busy="loading ? 'true' : 'false'"
    :data-client="client?.id"
  >
    <div
      v-if="loading"
      class="flex flex-col gap-3"
    >
      <Skeleton height="1.5rem" />

      <Skeleton height="5rem" />

      <Skeleton height="8rem" />
    </div>

    <p
      v-else-if="failed"
      class="text-body text-sm"
      role="alert"
    >
      {{ $t('fiscal.readiness.detail.loadFailed') }}
    </p>

    <div
      v-else-if="!client"
      class="flex flex-col gap-2"
    >
      <h2 class="text-heading text-base font-semibold">{{ $t('fiscal.readiness.detail.title') }}</h2>

      <p class="text-muted text-sm">{{ $t('fiscal.readiness.detail.emptyText') }}</p>
    </div>

    <template v-else>
      <div class="motion-enter flex flex-col gap-1">
        <span class="text-muted text-xs font-semibold tracking-wide uppercase">
          {{ $t('fiscal.readiness.detail.window', { days: client.window_days }) }}
        </span>

        <h2 class="text-heading text-lg font-semibold break-words">{{ client.name }}</h2>

        <p class="text-muted text-sm">
          {{ $t(`fiscal.sectors.${client.sector}`) }} · {{ $t(`fiscal.regimes.${client.regime}`) }} ·
          {{ client.system ?? $t('fiscal.readiness.table.noSystem') }}
        </p>
      </div>

      <dl
        class="bg-panel-muted motion-enter grid grid-cols-3 gap-3 rounded-md p-4"
        :style="{ '--reveal-index': 1 }"
      >
        <div class="flex min-w-0 flex-col gap-1">
          <dt class="text-muted text-xs font-medium">{{ $t('fiscal.readiness.detail.invoices') }}</dt>

          <dd class="text-heading text-lg font-semibold tabular-nums">{{ formatCount(client.invoices) }}</dd>
        </div>

        <div class="flex min-w-0 flex-col gap-1">
          <dt class="text-muted text-xs font-medium">{{ $t('fiscal.readiness.detail.complete') }}</dt>

          <dd class="text-heading text-lg font-semibold tabular-nums">{{ formatPercent(client.complete_pct) }}</dd>
        </div>

        <div class="flex min-w-0 flex-col gap-1">
          <dt class="text-muted text-xs font-medium">{{ $t('fiscal.readiness.detail.atRisk') }}</dt>

          <dd class="text-heading text-lg font-semibold tabular-nums">
            {{ formatCount(client.at_risk) }}
          </dd>
        </div>
      </dl>

      <MReadinessStatusChip :status="client.status" />

      <div class="flex flex-col gap-3">
        <h3 class="text-heading text-sm font-semibold">{{ $t('fiscal.readiness.detail.issues') }}</h3>

        <p
          v-if="client.invoices === 0"
          class="text-muted text-sm"
        >
          {{ $t('fiscal.readiness.detail.noNotes', { days: client.window_days }) }}
        </p>

        <p
          v-else-if="client.issues.length === 0"
          class="text-muted text-sm"
        >
          {{ $t('fiscal.readiness.detail.noIssues', { days: client.window_days }) }}
        </p>

        <ul
          v-else
          class="flex flex-col gap-2.5"
        >
          <li
            v-for="(issue, index) in client.issues"
            class="border-line motion-enter flex flex-col gap-1 border-t pt-2.5 first:border-t-0 first:pt-0"
            data-testid="readiness-issue"
            :key="issue.code"
            :data-code="issue.code"
            :data-risk="issue.risk ? 'true' : 'false'"
            :style="{ '--reveal-index': index + 2 }"
          >
            <span class="flex items-baseline gap-2">
              <strong
                :class="[
                  'shrink-0 text-sm font-semibold tabular-nums',
                  issue.risk ? 'bg-danger-50 text-danger-800 rounded-sm px-1.5 py-0.5' : 'text-heading',
                ]"
              >
                {{ $t('fiscal.readiness.issueCount', { count: formatCount(issue.invoices) }, issue.invoices) }}
              </strong>

              <span class="text-body text-sm">{{ $t(`fiscal.readiness.issues.${issue.code}`) }}</span>
            </span>

            <span
              v-if="issue.samples.length > 0"
              class="text-muted text-xs break-words"
            >
              {{ $t('fiscal.readiness.detail.samples') }}
              {{ issue.samples.map(sampleOf).join(' · ') }}
            </span>
          </li>
        </ul>
      </div>

      <Button
        class="w-full sm:w-fit"
        data-testid="readiness-notify"
        icon="pi pi-envelope"
        :label="$t('fiscal.readiness.detail.notify')"
        @click="emit('onNotify')"
      />

      <div class="border-line flex flex-col gap-3 border-t pt-4">
        <h3 class="text-heading text-sm font-semibold">{{ $t('fiscal.readiness.detail.timeline') }}</h3>

        <MClientTimeline
          :events
          :failed="eventsFailed"
          :loading="eventsLoading"
        />
      </div>
    </template>
  </section>
</template>
