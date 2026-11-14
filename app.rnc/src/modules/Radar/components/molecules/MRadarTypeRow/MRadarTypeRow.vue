<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { RadarBatchResult, RadarPartnerOption, RadarTypeDetail, RadarTypeRow } from '../../../interfaces';
import { useFiscalFormat } from '@FiscalModule';
import { RADAR_ERROR_KEYS, SPLIT_PAYMENT_RULE } from '../../../constants';
import { RADAR_KIND, RADAR_PERIOD } from '../../../enums';
import { GetRadarTypeService, ReferRadarBatchService } from '../../../services';

/*
 * One rule type of the Radar (rnc-2 R24–R27, the demo's `radarRow`): its kind, title and legal
 * text, how many clients and their range as an estimate (a year; the split payment's held cash a
 * month, with its "retidos na liquidação" line, R26), and the batch: a picker of the partners whose
 * specialty covers the rule (the API's list), "Encaminhar os N maiores" — N the chosen partner's
 * next batch, "Todos já encaminhados" at 0 —, how many it already got, and "N sem autorização ficam
 * fora do lote". "Ver clientes" opens the clients with their proof (`MRadarTypeDetail`). The row owns
 * its two calls and tells the view what went (`onSent`), which reads the Radar again.
 */
interface Props {
  /** False while the firm has `partners` off (rnc-4 R19): no batch, no picker, no "no partner". */
  canRefer?: boolean;
  type: RadarTypeRow;
}

interface BatchError {
  response?: { data?: { code?: string } };
}

const props = withDefaults(defineProps<Props>(), { canRefer: true });

const emit = defineEmits<{
  onSent: [type: RadarTypeRow, partner: RadarPartnerOption, result: RadarBatchResult];
}>();

const { formatCount, formatRange, formatShortRange } = useFiscalFormat();
const { t } = useI18n();

const chosenId = ref<number | null>(null);
const detail = ref<RadarTypeDetail | null>(null);
const detailFailed = ref(false);
const detailLoading = ref(false);
const error = ref<string | null>(null);
const isOpen = ref(false);
const isSending = ref(false);

const isRisk = computed(() => props.type.kind === RADAR_KIND.RISCO);
const isSplit = computed(() => props.type.rule === SPLIT_PAYMENT_RULE);
const period = computed(() => (props.type.period === RADAR_PERIOD.MES ? t('radar.perMonth') : t('radar.perYear')));

const chosen = computed<RadarPartnerOption | null>(
  () => props.type.partners.find((partner) => partner.id === chosenId.value) ?? props.type.partners[0] ?? null
);

const partnerOptions = computed(() =>
  props.type.partners.map((partner) => ({
    label: `${partner.name} · ${t(`partners.specialties.${partner.specialty}`)}`,
    value: partner.id,
  }))
);

const batch = computed(() => chosen.value?.batch ?? 0);

// HELPERS
function describeFailure(failure: BatchError): string {
  const knownRefusal = RADAR_ERROR_KEYS[failure.response?.data?.code ?? ''];

  return t(knownRefusal ?? 'radar.row.failed');
}

function loadDetail() {
  detailLoading.value = true;
  detailFailed.value = false;

  GetRadarTypeService(props.type.rule)
    .then((response) => (detail.value = response.data))
    .catch(() => (detailFailed.value = true))
    .finally(() => (detailLoading.value = false));
}

// EVENTS
function onPartner(value: number | string | null) {
  chosenId.value = value === null ? null : Number(value);
  error.value = null;
}

function onRefer() {
  const partner = chosen.value;

  if (!partner || batch.value === 0) return;

  isSending.value = true;
  error.value = null;

  ReferRadarBatchService(props.type.rule, partner.id)
    .then((response) => {
      detail.value = null;
      emit('onSent', props.type, partner, response.data);

      if (isOpen.value) loadDetail();
    })
    .catch((failure: BatchError) => (error.value = describeFailure(failure)))
    .finally(() => (isSending.value = false));
}

function onToggle() {
  isOpen.value = !isOpen.value;

  if (!isOpen.value || detail.value) return;

  loadDetail();
}
</script>

<template>
  <li
    class="border-line flex min-w-0 flex-col gap-4 border-t py-4 first:border-t-0 first:pt-0"
    data-testid="radar-type"
    :data-batch="batch"
    :data-kind="type.kind"
    :data-rule="type.rule"
  >
    <div
      class="flex min-w-0 flex-col gap-4 xl:grid xl:grid-cols-[minmax(0,5fr)_minmax(0,3fr)_minmax(0,4fr)] xl:items-start xl:gap-6"
    >
      <div class="flex min-w-0 flex-col gap-1.5">
        <span class="flex flex-wrap items-center gap-2">
          <span
            data-testid="radar-kind"
            :class="[
              'rounded-sm px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
              isRisk ? 'bg-warn-50 text-warn-900' : 'bg-success-50 text-success-800',
            ]"
          >
            {{ isRisk ? $t('radar.kinds.risco') : $t('radar.kinds.oportunidade') }}
          </span>

          <strong class="text-heading font-semibold break-words">{{ type.title }}</strong>
        </span>

        <span class="text-muted text-xs">{{ type.citation }}</span>

        <p
          v-if="isSplit"
          class="text-body text-sm"
          data-testid="radar-split-held"
          :data-high="type.range_cents[1]"
          :data-low="type.range_cents[0]"
        >
          <strong class="text-heading font-semibold tabular-nums">
            {{ $t('radar.row.heldValue', { range: formatShortRange(type.range_cents) }) }}
          </strong>

          {{ $t('radar.row.held') }}
        </p>
      </div>

      <div class="flex min-w-0 flex-col gap-1">
        <strong
          class="text-heading text-sm font-semibold"
          data-testid="radar-count"
        >
          {{ $t('radar.row.clients', { count: formatCount(type.clients) }, type.clients) }}
        </strong>

        <span class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
          <span
            class="text-heading text-sm tabular-nums"
            data-testid="radar-range"
            :data-high="type.range_cents[1]"
            :data-low="type.range_cents[0]"
          >
            {{ formatRange(type.range_cents) }}
          </span>

          <span class="text-muted text-xs">{{ period }}</span>

          <span
            class="bg-panel-muted text-muted rounded-sm px-1.5 py-0.5 text-xs font-medium"
            data-testid="radar-estimate"
          >
            {{ $t('fiscal.estimate') }}
          </span>
        </span>
      </div>

      <div
        v-if="canRefer"
        class="flex min-w-0 flex-col gap-2"
      >
        <template v-if="chosen">
          <label
            class="sr-only"
            :for="`radar-partner-${type.rule}`"
          >
            {{ $t('radar.row.partnerLabel', { title: type.title }) }}
          </label>

          <Select
            class="w-full"
            optionLabel="label"
            optionValue="value"
            size="small"
            :inputId="`radar-partner-${type.rule}`"
            :modelValue="chosen.id"
            :options="partnerOptions"
            @update:modelValue="onPartner"
          />

          <div class="flex flex-wrap items-center gap-2">
            <Button
              class="whitespace-nowrap"
              data-testid="radar-refer"
              size="small"
              :disabled="batch === 0"
              :icon="batch === 0 ? 'pi pi-check' : 'pi pi-send'"
              :label="batch === 0 ? $t('radar.row.allSent') : $t('radar.row.refer', { count: batch }, batch)"
              :loading="isSending"
              :severity="batch === 0 ? 'secondary' : undefined"
              @click="onRefer"
            />

            <span
              v-if="chosen.sent"
              class="bg-panel-muted text-body rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap"
              data-testid="radar-sent"
            >
              {{ $t('radar.row.sent', { count: formatCount(chosen.sent) }, chosen.sent) }}
            </span>
          </div>
        </template>

        <span
          v-else
          class="text-muted text-sm"
          data-testid="radar-no-partner"
        >
          {{ $t('radar.row.noPartner') }}
        </span>

        <span
          v-if="type.without_consent"
          class="text-muted text-xs"
          data-testid="radar-no-consent"
          :data-count="type.without_consent"
        >
          {{ $t('radar.row.withoutConsent', { count: formatCount(type.without_consent) }, type.without_consent) }}
        </span>

        <p
          v-if="error"
          class="bg-danger-50 text-danger-800 rounded-sm px-2 py-1 text-xs"
          role="alert"
        >
          {{ error }}
        </p>
      </div>
    </div>

    <Button
      text
      class="w-fit"
      data-testid="radar-open"
      severity="secondary"
      size="small"
      :aria-controls="`radar-detail-${type.rule}`"
      :aria-expanded="isOpen ? 'true' : 'false'"
      :icon="isOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
      :label="isOpen ? $t('radar.row.hideClients') : $t('radar.row.showClients')"
      @click="onToggle"
    />

    <div
      v-if="isOpen"
      :id="`radar-detail-${type.rule}`"
      class="bg-panel-muted motion-enter rounded-md p-4"
    >
      <MRadarTypeDetail
        :detail
        :failed="detailFailed"
        :loading="detailLoading"
        @onRetry="loadDetail"
      />
    </div>
  </li>
</template>
