<script setup lang="ts">
import { computed } from 'vue';
import type { DIAGNOSTIC_STEP } from '../../../enums';
import { PROGRESS_STEPS } from '../../../constants';

/* The four numbered steps (the demo's progress bar); the contact counts as part of step 1. */
const props = defineProps<{ step: DIAGNOSTIC_STEP }>();

const currentIndex = computed(() =>
  PROGRESS_STEPS.findIndex((entry) => (entry.steps as readonly DIAGNOSTIC_STEP[]).includes(props.step))
);
</script>

<template>
  <ol
    class="grid grid-cols-4 gap-2"
    data-testid="diagnostic-progress"
    :aria-label="$t('diagnostic.progress.label', { current: currentIndex + 1, total: PROGRESS_STEPS.length })"
  >
    <li
      v-for="(entry, index) in PROGRESS_STEPS"
      class="flex min-w-0 flex-col gap-2"
      :key="entry.key"
      :aria-current="index === currentIndex ? 'step' : undefined"
    >
      <span
        :class="[
          'motion-calm h-1.5 rounded-sm transition-colors',
          index <= currentIndex ? 'bg-primary-500' : 'bg-panel-muted',
        ]"
      />

      <span
        :class="[
          'flex flex-col gap-0.5 text-xs leading-tight font-semibold sm:flex-row sm:items-baseline sm:gap-1.5',
          index === currentIndex ? 'text-heading' : 'text-muted',
        ]"
      >
        <span class="tabular-nums">{{ index + 1 }}</span>

        <span class="min-w-0">
          {{ $t(`diagnostic.progress.${entry.key}`) }}
        </span>
      </span>
    </li>
  </ol>
</template>
