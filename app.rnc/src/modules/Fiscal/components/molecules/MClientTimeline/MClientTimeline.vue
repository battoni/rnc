<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { CentsRange, ClientEvent } from '../../../interfaces';
import { useFiscalFormat } from '../../../composables';
import { CLIENT_EVENT_TYPE } from '../../../enums';

/*
 * A client's timeline (rnc-2 R20, R22, D12), shared by the two fiscal screens: one sentence per line
 * from its type and the figures it was written on — the notice's counts, the review's Simples share
 * and range (an estimate) —, who wrote it and when, in São Paulo. The API keeps the facts
 * (`ClientEventResource`); the sentence is written here. The screen holding it loads the lines
 * (`useClientEvents`) and prepends the one it just wrote.
 */
interface Props {
  events: ClientEvent[];
  failed: boolean;
  loading: boolean;
}

defineProps<Props>();

const ICONS: Record<string, string> = {
  [CLIENT_EVENT_TYPE.NFE_NOTICE]: 'pi pi-envelope',
  [CLIENT_EVENT_TYPE.SUPPLIER_REVIEW]: 'pi pi-truck',
};

const { formatCount, formatMoment, formatPercent, formatRange } = useFiscalFormat();
const { t } = useI18n();

// HELPERS
function numberOf(value: unknown): number {
  return typeof value === 'number' ? value : 0;
}

function percentOf(value: unknown): number | null {
  return typeof value === 'number' ? value : null;
}

function rangeOf(value: unknown): CentsRange {
  const isRange = Array.isArray(value) && value.length === 2;

  return isRange ? [numberOf(value[0]), numberOf(value[1])] : [0, 0];
}

function sentenceOf(event: ClientEvent): string {
  if (event.type === CLIENT_EVENT_TYPE.NFE_NOTICE)
    return t('fiscal.timeline.notice', {
      invoices: formatCount(numberOf(event.data.invoices)),
      pct: formatPercent(percentOf(event.data.complete_pct)),
      risk: formatCount(numberOf(event.data.at_risk)),
    });

  if (event.type === CLIENT_EVENT_TYPE.SUPPLIER_REVIEW)
    return t('fiscal.timeline.review', {
      range: formatRange(rangeOf(event.data.lost_per_year_cents)),
      share: formatPercent(percentOf(event.data.simples_share)),
    });

  return t('fiscal.timeline.other', { type: event.type });
}

function signatureOf(event: ClientEvent): string {
  const at = formatMoment(event.at);

  return event.user ? t('fiscal.timeline.by', { at, name: event.user.name }) : t('fiscal.timeline.bySystem', { at });
}
</script>

<template>
  <div
    class="flex flex-col gap-3"
    data-testid="client-timeline"
    :aria-busy="loading ? 'true' : 'false'"
  >
    <Skeleton
      v-if="loading"
      height="3rem"
    />

    <p
      v-else-if="failed"
      class="text-body text-sm"
      role="alert"
    >
      {{ $t('fiscal.timeline.loadFailed') }}
    </p>

    <p
      v-else-if="events.length === 0"
      class="text-muted text-sm"
      data-testid="timeline-empty"
    >
      {{ $t('fiscal.timeline.empty') }}
    </p>

    <ol
      v-else
      class="flex flex-col gap-3"
    >
      <li
        v-for="event in events"
        class="motion-enter flex items-start gap-3"
        data-testid="timeline-line"
        :key="event.id"
        :data-type="event.type"
      >
        <span
          aria-hidden="true"
          class="bg-primary-50 text-primary-950 flex size-7 shrink-0 items-center justify-center rounded-md"
        >
          <i :class="[ICONS[event.type] ?? 'pi pi-circle', 'text-xs']" />
        </span>

        <span class="flex min-w-0 flex-col gap-0.5">
          <span class="text-body text-sm break-words">{{ sentenceOf(event) }}</span>

          <span class="text-muted text-xs tabular-nums">{{ signatureOf(event) }}</span>
        </span>
      </li>
    </ol>
  </div>
</template>
