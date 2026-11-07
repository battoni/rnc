<script setup lang="ts">
import type { DiagnosticResult } from '../../../interfaces';
import { RESULT_KIND } from '../../../enums';

/*
 * The result (rnc-1 R13): "Encontramos N oportunidades e M riscos", then each item with its kind,
 * its title and its R$ range — always an estimate. The detail of each item is not here at all: the
 * API never sends it, and the line under each title says where it is (the meeting).
 */
const props = defineProps<{
  profileLine: string;
  result: DiagnosticResult;
}>();
</script>

<template>
  <section
    class="flex flex-col gap-5"
    data-testid="result"
  >
    <div class="flex flex-col gap-2">
      <h2
        class="text-heading text-2xl font-bold tracking-tight text-balance sm:text-3xl"
        data-testid="result-summary"
      >
        {{
          $t('diagnostic.result.summary', {
            opportunities: $t('diagnostic.result.opportunities', props.result.opportunities),
            risks: $t('diagnostic.result.risks', props.result.risks),
          })
        }}
      </h2>

      <p class="text-muted text-sm">
        {{ $t('diagnostic.result.profile', { profile: props.profileLine }) }}
      </p>
    </div>

    <!-- The items land one after the other under the summary, so the count reads first. -->
    <ul class="flex flex-col gap-3">
      <li
        v-for="(item, index) in props.result.items"
        class="motion-enter bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-4 sm:flex-row sm:items-center sm:gap-5 sm:p-5"
        data-testid="result-item"
        :key="item.title"
        :data-kind="item.kind"
        :style="{ '--reveal-index': index + 1 }"
      >
        <span
          :class="[
            'w-fit shrink-0 rounded-sm px-2 py-1 text-xs font-semibold',
            item.kind === RESULT_KIND.OPPORTUNITY ? 'bg-success-100 text-success-800' : 'bg-warn-100 text-warn-800',
          ]"
        >
          {{
            item.kind === RESULT_KIND.OPPORTUNITY ? $t('diagnostic.result.opportunity') : $t('diagnostic.result.risk')
          }}
        </span>

        <span class="flex min-w-0 flex-1 flex-col gap-1">
          <strong class="text-heading">{{ item.title }}</strong>

          <span
            class="text-muted flex items-center gap-1.5 text-xs"
            data-testid="locked"
          >
            <i
              aria-hidden="true"
              class="pi pi-lock text-subtle text-xs"
            />

            {{ $t('diagnostic.result.locked') }}
          </span>
        </span>

        <span class="text-heading shrink-0 text-sm font-semibold tabular-nums sm:text-right">
          {{ item.range_label }}

          <span class="text-muted block text-xs font-normal">
            {{ $t('diagnostic.result.estimate') }}
          </span>
        </span>
      </li>
    </ul>
  </section>
</template>
