<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ClientEvent, SupplierCreditClient } from '../../../interfaces';
import { formatReal } from '@Helpers';
import { useClientEvents, useFiscalFormat } from '../../../composables';
import { BAR_LABEL_MIN_SHARE, FISCAL_ERROR_KEYS } from '../../../constants';
import { ProposeReviewService } from '../../../services';

/*
 * One client of the supplier credit (rnc-2 R21–R22, the demo's `fornRow`): its profile and monthly
 * purchases, the purchases split Simples/MEI · regime normal (two parts, D8), the credit lost per
 * year as a range labelled an estimate with a bar against the list's top, and "Propor revisão" —
 * disabled with "Sem fornecedor do Simples" at 0 %. Proposing writes ONE `fornecedores.revisao`
 * line; the row then reads "Revisão proposta" and can open the client's timeline. A refusal the API
 * names by `code` is said on the row. The row owns its call and tells the view (`onProposed`).
 */
interface Props {
  client: SupplierCreditClient;
  /** The top of the list's largest range, the 100 % of the row's bar. */
  top: number;
}

interface ReviewError {
  response?: { data?: { code?: string } };
}

const props = defineProps<Props>();

const emit = defineEmits<{
  onProposed: [client: SupplierCreditClient, event: ClientEvent];
}>();

const MIN_BAR_PCT = 2;

const { events, failed: eventsFailed, load: loadEvents, loading: eventsLoading } = useClientEvents();
const { formatPercent, formatRange } = useFiscalFormat();
const { t } = useI18n();

const error = ref<string | null>(null);
const isHistoryOpen = ref(false);
const isProposing = ref(false);

const canPropose = computed(() => props.client.simples_share > 0);
const hasLoss = computed(() => props.client.lost_per_year_cents[1] > 0);

const barWidth = computed(() => {
  const high = props.client.lost_per_year_cents[1];

  const isFlat = !hasLoss.value || props.top <= 0;

  if (isFlat) return 0;

  return Math.max(MIN_BAR_PCT, (high / props.top) * 100);
});

const parts = computed(() =>
  [
    { key: 'simples', share: props.client.simples_share, tone: 'bg-warn-200 text-warn-900' },
    { key: 'normal', share: props.client.normal_share, tone: 'bg-primary-100 text-primary-950' },
  ].filter((part) => part.share > 0)
);

// HELPERS
function describeFailure(failure: ReviewError): string {
  const knownRefusal = FISCAL_ERROR_KEYS[failure.response?.data?.code ?? ''];

  return t(knownRefusal ?? 'fiscal.suppliers.row.failed');
}

// EVENTS
function onPropose() {
  if (!canPropose.value) return;

  isProposing.value = true;
  error.value = null;

  ProposeReviewService(props.client.id)
    .then((response) => emit('onProposed', props.client, response.data))
    .catch((failure: ReviewError) => (error.value = describeFailure(failure)))
    .finally(() => (isProposing.value = false));
}

function onToggleHistory() {
  isHistoryOpen.value = !isHistoryOpen.value;

  if (!isHistoryOpen.value) return;

  loadEvents(props.client.id);
}
</script>

<template>
  <li
    class="border-line flex min-w-0 flex-col gap-4 border-t py-4 first:border-t-0 first:pt-0 lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,4fr)_auto] lg:items-center lg:gap-6"
    data-testid="supplier-row"
    :data-id="client.id"
    :data-simples="client.simples_share"
  >
    <div class="flex min-w-0 flex-col gap-2">
      <div class="flex min-w-0 flex-col gap-0.5">
        <strong class="text-heading font-semibold break-words">{{ client.name }}</strong>

        <span class="text-muted text-xs">
          {{ $t(`fiscal.sectors.${client.sector}`) }} · {{ $t(`fiscal.regimes.${client.regime}`) }} ·
          {{ $t('fiscal.suppliers.row.purchases', { value: formatReal(client.monthly_purchases_cents) }) }}
        </span>
      </div>

      <span
        class="flex h-6 w-full gap-0.5 overflow-hidden rounded-sm"
        role="img"
        :aria-label="
          $t('fiscal.suppliers.row.bar', {
            normal: formatPercent(client.normal_share),
            simples: formatPercent(client.simples_share),
          })
        "
      >
        <span
          v-for="part in parts"
          data-testid="supplier-part"
          :key="part.key"
          :class="[
            'motion-wipe-y flex min-w-0 items-center justify-center text-xs font-semibold tabular-nums',
            part.tone,
          ]"
          :data-part="part.key"
          :data-share="part.share"
          :style="{ flexGrow: part.share }"
        >
          {{ part.share >= BAR_LABEL_MIN_SHARE ? formatPercent(part.share) : '' }}
        </span>
      </span>
    </div>

    <div class="flex min-w-0 flex-col gap-1">
      <span class="text-muted text-xs">
        {{ $t('fiscal.suppliers.row.lost', { share: formatPercent(client.simples_share) }) }}
      </span>

      <span class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <strong
          class="text-heading text-sm font-semibold tabular-nums"
          data-testid="supplier-range"
        >
          {{ formatRange(client.lost_per_year_cents) }}
        </strong>

        <span
          class="bg-panel-muted text-muted rounded-sm px-1.5 py-0.5 text-xs font-medium"
          data-testid="supplier-estimate"
        >
          {{ $t('fiscal.estimate') }}
        </span>
      </span>

      <span
        aria-hidden="true"
        class="bg-panel-muted h-1.5 w-full overflow-hidden rounded-sm"
      >
        <span
          class="bg-danger-600 motion-wipe-y block h-full rounded-sm"
          :style="{ width: `${barWidth}%` }"
        />
      </span>
    </div>

    <div class="flex min-w-0 flex-col items-start gap-2 lg:items-end">
      <template v-if="client.proposed">
        <span
          class="bg-success-50 text-success-800 motion-enter flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-semibold whitespace-nowrap"
          data-testid="supplier-proposed"
        >
          <i
            aria-hidden="true"
            class="pi pi-check"
          />

          {{ $t('fiscal.suppliers.row.proposed') }}
        </span>

        <Button
          text
          data-testid="supplier-history"
          severity="secondary"
          size="small"
          :aria-expanded="isHistoryOpen ? 'true' : 'false'"
          :label="isHistoryOpen ? $t('fiscal.suppliers.row.hideHistory') : $t('fiscal.suppliers.row.history')"
          @click="onToggleHistory"
        />
      </template>

      <template v-else>
        <Button
          class="whitespace-nowrap"
          data-testid="supplier-propose"
          severity="secondary"
          size="small"
          :aria-describedby="canPropose ? undefined : `supplier-hint-${client.id}`"
          :disabled="!canPropose"
          :icon="canPropose ? 'pi pi-send' : undefined"
          :label="canPropose ? $t('fiscal.suppliers.row.propose') : $t('fiscal.suppliers.row.noSimples')"
          :loading="isProposing"
          @click="onPropose"
        />

        <span
          v-if="!canPropose"
          :id="`supplier-hint-${client.id}`"
          class="sr-only"
        >
          {{ $t('fiscal.suppliers.row.noSimplesHint') }}
        </span>
      </template>

      <p
        v-if="error"
        class="bg-danger-50 text-danger-800 max-w-64 rounded-sm px-2 py-1 text-xs"
        role="alert"
      >
        {{ error }}
      </p>
    </div>

    <div
      v-if="isHistoryOpen"
      class="bg-panel-muted rounded-md p-4 lg:col-span-3"
    >
      <MClientTimeline
        :events
        :failed="eventsFailed"
        :loading="eventsLoading"
      />
    </div>
  </li>
</template>
