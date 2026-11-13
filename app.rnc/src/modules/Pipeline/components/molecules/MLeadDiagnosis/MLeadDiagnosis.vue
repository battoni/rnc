<script setup lang="ts">
import type { LeadDiagnosis } from '../../../interfaces';
import { RESULT_KIND } from '@DiagnosticModule';

/*
 * The full diagnostic as the team reads it (rnc-1 R19): every item with its R$ range, the detail and
 * the cited rule the visitor only gets in the meeting. Empty until the profile reached the result.
 */
const props = defineProps<{
  diagnosis: LeadDiagnosis;
}>();
</script>

<template>
  <div class="flex flex-col gap-3">
    <p
      v-if="props.diagnosis.items.length === 0"
      class="text-muted text-sm"
      data-testid="diagnosis-incomplete"
    >
      {{ $t('pipeline.lead.diagnosis.incomplete') }}
    </p>

    <template v-else>
      <p class="text-muted flex items-center gap-1.5 text-xs">
        <i
          aria-hidden="true"
          class="pi pi-lock-open text-subtle text-xs"
        />

        {{ $t('pipeline.lead.diagnosis.staffOnly') }}
      </p>

      <ul class="flex flex-col gap-3">
        <li
          v-for="item in props.diagnosis.items"
          class="border-line flex flex-col gap-2 rounded-md border p-3.5"
          data-testid="diagnosis-item"
          :key="item.id"
          :data-kind="item.kind"
        >
          <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <span class="flex min-w-0 flex-col gap-1.5">
              <span
                :class="[
                  'w-fit rounded-sm px-2 py-0.5 text-xs font-semibold',
                  item.kind === RESULT_KIND.OPPORTUNITY
                    ? 'bg-success-100 text-success-800'
                    : 'bg-warn-100 text-warn-800',
                ]"
              >
                {{
                  item.kind === RESULT_KIND.OPPORTUNITY
                    ? $t('pipeline.lead.diagnosis.opportunity')
                    : $t('pipeline.lead.diagnosis.risk')
                }}
              </span>

              <strong class="text-heading">{{ item.title }}</strong>
            </span>

            <span class="text-heading shrink-0 text-sm font-semibold tabular-nums">
              {{ item.range_label }}

              <span class="text-muted block text-xs font-normal">{{ $t('pipeline.lead.diagnosis.estimate') }}</span>
            </span>
          </div>

          <p
            class="text-body text-sm"
            data-testid="diagnosis-detail"
          >
            {{ item.detail }}
          </p>

          <p class="text-muted text-xs">{{ $t('pipeline.lead.diagnosis.rule', { rule: item.rule }) }}</p>
        </li>
      </ul>
    </template>
  </div>
</template>
