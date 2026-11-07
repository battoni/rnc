<script setup lang="ts">
import { READINESS_STATUS } from '../../../enums';

/*
 * A client's readiness in one word (rnc-2 R18): Crítico · Atenção · OK · Sem notas importadas, each
 * in its state tone — the demo's chips. The colour never carries the status alone: the word is there.
 */
interface Props {
  status: READINESS_STATUS;
}

defineProps<Props>();

const TONE_CLASSES: Record<READINESS_STATUS, string> = {
  [READINESS_STATUS.ATENCAO]: 'bg-warn-50 text-warn-900',
  [READINESS_STATUS.CRITICO]: 'bg-danger-50 text-danger-800',
  [READINESS_STATUS.OK]: 'bg-success-50 text-success-800',
  [READINESS_STATUS.SEM_NOTAS]: 'bg-panel-muted text-muted',
};
</script>

<template>
  <span
    data-testid="readiness-status"
    :class="['w-fit rounded-sm px-2 py-0.5 text-xs font-semibold whitespace-nowrap', TONE_CLASSES[status]]"
    :data-status="status"
  >
    {{ $t(`fiscal.statuses.${status}`) }}
  </span>
</template>
