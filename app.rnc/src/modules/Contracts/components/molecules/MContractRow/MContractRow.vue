<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ContractAlertRow, LegalPartner } from '../../../interfaces';
import { formatReal } from '@Helpers';
import {
  CONTRACT_REFERRAL_ERROR_KEYS,
  EXPOSURE_BAR_MIN_PERCENT,
  TRANSITION_FIRST_YEAR,
  TRANSITION_LAST_YEAR,
} from '../../../constants';
import { ReferContractService } from '../../../services';

/*
 * One at-risk contract of the alert (rnc-3 R19–R22, the demo's `ctRow`): the client, "<tipo> · desde
 * <início> · <valor>/ano · até <fim>", the '27–'33 chips lit on the years it is exposed
 * (`max(início, 2027)` to `min(fim, 2033)`, the API's `years_exposed`), "Valor × anos expostos" and its
 * bar against the most exposed row (never under 2 %). Then the legal referral: "Com <parceiro>" once
 * sent; else the picker of the API's legal partners and "Encaminhar ao jurídico" — disabled, with the
 * demo's reason, when the client has not consented (null counts as no) —; "Sem parceiro jurídico na
 * rede." without one. "Cláusula incluída" asks first, on the row itself (the mark cannot be undone
 * here — W101 in rnc-3's state.md: a ConfirmPopup closes on the first scroll of the list, so a row near the
 * fold never got its answer), then hands the contract to the view, which marks it and reads the
 * alert again. The row owns its referral call and tells the view what went (`onSent`) or that the
 * list is stale (`onStale`, a 409).
 */
interface Props {
  /** False while the firm has `partners` off (rnc-4 R19): the row offers no legal referral. */
  canRefer?: boolean;
  contract: ContractAlertRow;
  isMarking?: boolean;
  partners: LegalPartner[];
  topExposureCents: number;
}

interface ReferError {
  response?: { data?: { code?: string }; status?: number };
}

const PERCENT = 100;
const STALE_STATUS = 409;
const YEARS = Array.from(
  { length: TRANSITION_LAST_YEAR - TRANSITION_FIRST_YEAR + 1 },
  (_, index) => TRANSITION_FIRST_YEAR + index
);

const props = withDefaults(defineProps<Props>(), { canRefer: true, isMarking: false });

const emit = defineEmits<{
  onClause: [contract: ContractAlertRow];
  onSent: [contract: ContractAlertRow, partner: LegalPartner];
  onStale: [];
}>();

const { t } = useI18n();

const chosenId = ref<number | null>(null);
const error = ref<string | null>(null);
const isConfirming = ref(false);
const isSending = ref(false);

const firstExposed = computed(() => Math.max(props.contract.start_year, TRANSITION_FIRST_YEAR));
const lastExposed = computed(() => Math.min(props.contract.end_year, TRANSITION_LAST_YEAR));

const consentReason = computed<string | null>(() => {
  const consent = props.contract.client.partner_consent;

  if (consent === true) return null;

  return consent === false ? t('contracts.row.consentRefused') : t('contracts.row.consentMissing');
});

const chosen = computed<LegalPartner | null>(
  () => props.partners.find((partner) => partner.id === chosenId.value) ?? props.partners[0] ?? null
);

const partnerOptions = computed(() =>
  props.partners.map((partner) => ({
    label: `${partner.name} · ${t(`partners.specialties.${partner.specialty}`)}`,
    value: partner.id,
  }))
);

const sentTo = computed(() => (props.contract.referred_to ?? []).map((partner) => partner.name).join(', '));

const barWidth = computed(() => {
  const share = props.topExposureCents > 0 ? (props.contract.exposure_cents / props.topExposureCents) * PERCENT : 0;

  return `${Number(Math.max(EXPOSURE_BAR_MIN_PERCENT, share).toFixed(1))}%`;
});

const yearsLabel = computed(() =>
  props.contract.years_exposed > 0
    ? t('contracts.row.years', { from: firstExposed.value, to: lastExposed.value })
    : t('contracts.row.notExposed')
);

// HELPERS
function isExposedIn(year: number): boolean {
  return year >= firstExposed.value && year <= lastExposed.value;
}

function shortYear(year: number): string {
  return `'${String(year).slice(2)}`;
}

function describeFailure(failure: ReferError): string {
  const knownRefusal = CONTRACT_REFERRAL_ERROR_KEYS[failure.response?.data?.code ?? ''];

  return t(knownRefusal ?? 'contracts.row.failed');
}

// EVENTS
function onPartner(value: number | string | null) {
  chosenId.value = value === null ? null : Number(value);
  error.value = null;
}

function onRefer() {
  const partner = chosen.value;

  if (!partner || consentReason.value) return;

  isSending.value = true;
  error.value = null;

  ReferContractService(props.contract.id, partner.id)
    .then(() => emit('onSent', props.contract, partner))
    .catch((failure: ReferError) => {
      error.value = describeFailure(failure);

      if (failure.response?.status === STALE_STATUS) emit('onStale');
    })
    .finally(() => (isSending.value = false));
}

function onClauseConfirmed() {
  isConfirming.value = false;
  emit('onClause', props.contract);
}
</script>

<template>
  <li
    class="border-line flex min-w-0 flex-col gap-4 border-t py-4 first:border-t-0 first:pt-0 xl:grid xl:grid-cols-[minmax(0,5fr)_minmax(0,3fr)_minmax(0,4fr)] xl:items-start xl:gap-6"
    data-testid="contract"
    :data-consent="String(contract.client.partner_consent)"
    :data-end="contract.end_year"
    :data-exposure="contract.exposure_cents"
    :data-id="contract.id"
  >
    <div class="flex min-w-0 flex-col gap-1.5">
      <strong class="text-heading font-semibold break-words">{{ contract.client.name }}</strong>

      <span
        class="text-muted text-sm tabular-nums"
        data-testid="contract-line"
      >
        {{
          $t('contracts.row.line', {
            end: contract.end_year,
            kind: $t(`contracts.kinds.${contract.kind}`),
            start: contract.start_year,
            value: formatReal(contract.annual_value_cents),
          })
        }}
      </span>

      <ol
        class="flex flex-wrap gap-1"
        :aria-label="yearsLabel"
      >
        <li
          v-for="year in YEARS"
          aria-hidden="true"
          data-testid="contract-year"
          :key="year"
          :class="[
            'rounded-sm px-1.5 py-0.5 text-xs font-semibold tabular-nums',
            isExposedIn(year) ? 'bg-warn-50 text-warn-900' : 'bg-panel-muted text-muted',
          ]"
          :data-lit="String(isExposedIn(year))"
        >
          {{ shortYear(year) }}
        </li>
      </ol>
    </div>

    <div class="flex min-w-0 flex-col gap-1">
      <span class="text-muted text-xs">{{ $t('contracts.row.exposure') }}</span>

      <strong
        class="text-heading text-sm font-semibold tabular-nums"
        data-testid="contract-exposure"
        :data-value="contract.exposure_cents"
      >
        {{ formatReal(contract.exposure_cents) }}
      </strong>

      <span class="text-muted text-xs">
        {{ $t('contracts.row.yearsExposed', { count: contract.years_exposed }, contract.years_exposed) }}
      </span>

      <span
        aria-hidden="true"
        class="bg-panel-muted block h-1.5 w-full overflow-hidden rounded-full"
      >
        <span
          class="bg-warn-600 motion-wipe-y block h-full rounded-full"
          data-testid="contract-bar"
          :style="{ width: barWidth }"
        />
      </span>
    </div>

    <div class="flex min-w-0 flex-col gap-2">
      <template v-if="canRefer">
        <span
          v-if="sentTo"
          class="bg-success-50 text-success-800 w-fit rounded-sm px-2 py-0.5 text-xs font-semibold break-words"
          data-testid="contract-sent"
        >
          {{ $t('contracts.row.sent', { partners: sentTo }) }}
        </span>

        <template v-else-if="chosen">
          <p
            v-if="consentReason"
            :id="`contract-consent-${contract.id}`"
            class="text-muted text-xs"
            data-testid="contract-consent-blocked"
          >
            {{ consentReason }}
          </p>

          <template v-else>
            <label
              class="sr-only"
              :for="`contract-partner-${contract.id}`"
            >
              {{ $t('contracts.row.partnerLabel', { client: contract.client.name }) }}
            </label>

            <Select
              class="w-full"
              optionLabel="label"
              optionValue="value"
              size="small"
              :inputId="`contract-partner-${contract.id}`"
              :modelValue="chosen.id"
              :options="partnerOptions"
              @update:modelValue="onPartner"
            />
          </template>

          <Button
            class="w-full whitespace-nowrap sm:w-fit"
            data-testid="contract-refer"
            icon="pi pi-send"
            size="small"
            :aria-describedby="consentReason ? `contract-consent-${contract.id}` : undefined"
            :disabled="Boolean(consentReason)"
            :label="$t('contracts.row.refer')"
            :loading="isSending"
            @click="onRefer"
          />
        </template>

        <span
          v-else
          class="text-muted text-sm"
          data-testid="contract-no-partner"
        >
          {{ $t('contracts.row.noPartner') }}
        </span>
      </template>

      <p
        v-if="error"
        class="bg-danger-50 text-danger-800 rounded-sm px-2 py-1 text-xs"
        role="alert"
      >
        {{ error }}
      </p>

      <div
        v-if="isConfirming"
        class="border-line motion-enter flex flex-col gap-2 rounded-md border p-3"
        data-testid="contract-clause-confirm"
      >
        <p class="text-body text-xs">{{ $t('contracts.row.clauseConfirm', { client: contract.client.name }) }}</p>

        <div class="flex flex-wrap gap-2">
          <Button
            data-testid="contract-clause-yes"
            icon="pi pi-check"
            size="small"
            :label="$t('contracts.row.clauseYes')"
            @click="onClauseConfirmed"
          />

          <Button
            data-testid="contract-clause-no"
            severity="secondary"
            size="small"
            :label="$t('contracts.row.cancel')"
            @click="isConfirming = false"
          />
        </div>
      </div>

      <Button
        v-else
        text
        class="w-fit"
        data-testid="contract-clause"
        icon="pi pi-check"
        severity="secondary"
        size="small"
        :label="$t('contracts.row.clause')"
        :loading="isMarking"
        @click="isConfirming = true"
      />
    </div>
  </li>
</template>
