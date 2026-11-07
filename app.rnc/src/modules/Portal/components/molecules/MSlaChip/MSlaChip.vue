<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ReferralSla } from '../../../interfaces';
import { SLA_HOURS } from '../../../constants';
import { SLA_STATE } from '../../../enums';

/*
 * The first-contact chip (rnc-2 R8, the demo's `slaChip`): the sentence of the referral's state
 * with the business hours the API counted against the 24-hour target. The API decides the state
 * and the hours (`ReferralSla`); the chip only speaks them. Shared — the partner's cards, the lead's
 * routing panel and the trail wear the same one, so Franco and the partner read the same words.
 */
const props = defineProps<{
  sla: ReferralSla;
}>();

const TONES: Record<SLA_STATE, string> = {
  [SLA_STATE.OK]: 'success',
  [SLA_STATE.ATENCAO]: 'warn',
  [SLA_STATE.ESTOURADO]: 'danger',
  [SLA_STATE.CUMPRIDO]: 'success',
  [SLA_STATE.FORA]: 'danger',
  [SLA_STATE.ENCERRADO]: 'muted',
};

const TONE_CLASSES: Record<string, string> = {
  danger: 'bg-danger-50 text-danger-800',
  muted: 'bg-panel-muted text-muted',
  success: 'bg-success-50 text-success-800',
  warn: 'bg-warn-50 text-warn-900',
};

const { locale, t } = useI18n();

const tone = computed(() => TONES[props.sla.state] ?? 'muted');

const sentence = computed(() =>
  t(`portal.sla.${props.sla.state}`, {
    hours: new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(props.sla.hours),
    target: SLA_HOURS,
  })
);
</script>

<template>
  <span
    data-testid="sla-chip"
    :class="['w-fit rounded-sm px-2 py-0.5 text-xs font-semibold', TONE_CLASSES[tone]]"
    :data-sla="props.sla.state"
    :data-tone="tone"
  >
    {{ sentence }}
  </span>
</template>
