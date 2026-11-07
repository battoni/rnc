<script setup lang="ts">
import { computed } from 'vue';
import type { RadarClient, RadarTypeDetail } from '../../../interfaces';
import { formatReal } from '@Helpers';
import { useFiscalFormat } from '@FiscalModule';
import { SPLIT_PAYMENT_RULE } from '../../../constants';
import { RADAR_EVIDENCE_SOURCE, RADAR_PERIOD } from '../../../enums';

/*
 * One Radar row opened (rnc-2 R25, the demo's type detail): the rule's text, then its clients,
 * biggest range first — each with its profile, its consent ("Sem autorização" when not given: such
 * a client stays out of every batch, R28), its range as an estimate and its proof. A proof from the
 * XMLs says what they showed (readiness counts, the measured B2B share against the declared one, or
 * the cash held per month) with up to three access keys; any other item says "do cadastro".
 * Presentational: the row loads the detail and passes it here.
 */
interface Props {
  detail: RadarTypeDetail | null;
  failed: boolean;
  loading: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  onRetry: [];
}>();

const { formatCount, formatPercent, formatRange } = useFiscalFormat();

const isMonthly = computed(() => props.detail?.type.period === RADAR_PERIOD.MES);

// HELPERS
function proofKey(client: RadarClient): string {
  if (client.evidence.source === RADAR_EVIDENCE_SOURCE.CADASTRO) return 'radar.detail.proof.cadastro';

  if (props.detail?.type.rule === SPLIT_PAYMENT_RULE) return 'radar.detail.proof.split';

  return client.evidence.invoices === undefined ? 'radar.detail.proof.share' : 'radar.detail.proof.readiness';
}

function proofValues(client: RadarClient): Record<string, string> {
  const { evidence } = client;

  return {
    atRisk: formatCount(evidence.at_risk ?? 0),
    complete: formatCount(evidence.complete ?? 0),
    declared: formatPercent(evidence.declared_b2b_share ?? null),
    days: formatCount(evidence.window_days ?? 0),
    documents: formatCount(evidence.documents ?? 0),
    held: evidence.held_per_month_cents ? formatRange(evidence.held_per_month_cents) : '',
    invoices: formatCount(evidence.invoices ?? 0),
    monthly: formatReal(evidence.monthly_b2b_cents ?? 0),
    share: formatPercent(evidence.b2b_share ?? null),
  };
}
</script>

<template>
  <div
    class="flex min-w-0 flex-col gap-4"
    data-testid="radar-detail"
  >
    <div
      v-if="loading && !detail"
      class="flex flex-col gap-2"
    >
      <Skeleton height="3rem" />

      <Skeleton height="3rem" />
    </div>

    <div
      v-else-if="failed && !detail"
      class="flex flex-wrap items-center gap-3"
      role="alert"
    >
      <p class="text-body text-sm">{{ $t('radar.detail.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        size="small"
        :label="$t('radar.retry')"
        @click="emit('onRetry')"
      />
    </div>

    <template v-else-if="detail">
      <p class="text-body text-sm">{{ detail.type.detail }}</p>

      <p
        v-if="detail.clients.length === 0"
        class="text-muted text-sm"
        data-testid="radar-detail-empty"
      >
        {{ $t('radar.detail.empty') }}
      </p>

      <ul
        v-else
        class="flex min-w-0 flex-col"
      >
        <li
          v-for="client in detail.clients"
          class="border-line motion-enter flex min-w-0 flex-col gap-2 border-t py-3 first:border-t-0 first:pt-0 md:grid md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] md:gap-4"
          data-testid="radar-client"
          :key="client.id"
          :data-id="client.id"
          :data-source="client.evidence.source"
        >
          <div class="flex min-w-0 flex-col gap-1">
            <span class="flex flex-wrap items-center gap-2">
              <strong class="text-heading text-sm font-semibold break-words">{{ client.name }}</strong>

              <span
                v-if="client.partner_consent !== true"
                class="bg-warn-50 text-warn-900 rounded-sm px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                data-testid="radar-client-no-consent"
              >
                {{ $t('radar.detail.noConsent') }}
              </span>
            </span>

            <span class="text-muted text-xs">
              {{ $t(`fiscal.sectors.${client.sector}`) }} · {{ $t(`fiscal.regimes.${client.regime}`) }} ·
              {{ $t(`diagnostic.bands.${client.band}`) }}
            </span>

            <span
              data-testid="radar-proof"
              :class="[
                'w-fit rounded-sm px-1.5 py-0.5 text-xs',
                client.evidence.source === RADAR_EVIDENCE_SOURCE.NOTAS
                  ? 'bg-primary-50 text-primary-950'
                  : 'bg-panel-muted text-muted',
              ]"
            >
              {{ $t(proofKey(client), proofValues(client)) }}
            </span>

            <ul
              v-if="client.keys.length"
              class="flex min-w-0 flex-col gap-0.5"
              :aria-label="$t('radar.detail.keys')"
            >
              <li
                v-for="key in client.keys"
                class="text-muted font-mono text-xs break-all"
                data-testid="radar-key"
                :key
              >
                {{ key }}
              </li>
            </ul>
          </div>

          <span class="flex flex-wrap items-baseline gap-x-2 gap-y-1 md:justify-end">
            <strong
              class="text-heading text-sm font-semibold tabular-nums"
              data-testid="radar-client-range"
            >
              {{ formatRange(client.range_cents) }}
            </strong>

            <span class="text-muted text-xs">
              {{ isMonthly ? $t('radar.perMonth') : $t('radar.perYear') }}
            </span>
          </span>
        </li>
      </ul>
    </template>
  </div>
</template>
