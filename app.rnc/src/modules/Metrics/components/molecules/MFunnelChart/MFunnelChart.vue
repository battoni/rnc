<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FunnelStepCount } from '../../../interfaces';

/*
 * The funnel's seven steps as horizontal bars (rnc-1 R24, the demo's `funnelChart()`): each bar is
 * its count against the top of the funnel, and from the second step on a line says how much of the
 * step before did not come through. Plain HTML bars: readable by a screen reader as a list, no chart
 * library for seven numbers.
 */
interface Props {
  steps: FunnelStepCount[];
}

const props = defineProps<Props>();

const { locale } = useI18n();

const rows = computed(() => {
  const top = props.steps[0]?.count ?? 0;

  return props.steps.map((step, index) => {
    const previous = index === 0 ? null : (props.steps[index - 1]?.count ?? 0);

    return {
      ...step,
      lost: previous === null ? 0 : Math.max(0, previous - step.count),
      width: top > 0 ? Math.round((step.count / top) * 1000) / 10 : 0,
    };
  });
});

// HELPERS
function formatCount(value: number): string {
  return new Intl.NumberFormat(locale.value).format(value);
}

function formatPercent(value: number): string {
  return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1, minimumFractionDigits: 1 }).format(value)}%`;
}
</script>

<template>
  <ol
    class="flex flex-col gap-4"
    :aria-label="$t('metrics.chartLabel')"
  >
    <li
      v-for="(step, index) in rows"
      class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 sm:grid-cols-[11rem_minmax(0,1fr)_5rem]"
      data-testid="funnel-step"
      :key="step.key"
      :data-step="step.key"
      :data-value="step.count"
    >
      <span class="text-body col-start-1 row-start-1 flex min-w-0 items-center gap-2 text-sm font-medium">
        <span
          aria-hidden="true"
          class="bg-panel-muted text-muted grid size-5 shrink-0 place-items-center rounded-sm text-[0.6875rem] font-semibold tabular-nums"
        >
          {{ index + 1 }}
        </span>

        <span class="truncate">{{ $t(`metrics.steps.${step.key}`) }}</span>
      </span>

      <strong
        class="text-heading col-start-2 row-start-1 text-right text-base font-semibold tabular-nums sm:col-start-3 sm:text-lg"
        data-testid="funnel-count"
      >
        {{ formatCount(step.count) }}
      </strong>

      <span
        aria-hidden="true"
        class="bg-panel-muted col-span-2 row-start-2 block h-3 overflow-hidden rounded-full sm:col-span-1 sm:col-start-2 sm:row-start-1 sm:h-4"
      >
        <span
          class="from-primary-600 to-primary-400 motion-settle motion-ease-settle block h-full rounded-full bg-linear-to-r transition-[width]"
          data-testid="funnel-bar"
          :style="{ minWidth: step.count > 0 ? '0.375rem' : '0', width: `${step.width}%` }"
        />
      </span>

      <span
        v-if="step.drop_off !== null"
        class="text-muted col-span-2 row-start-3 text-xs tabular-nums sm:col-start-2 sm:col-end-4 sm:row-start-2"
        data-testid="funnel-drop"
        :data-lost="step.lost"
      >
        {{ $t('metrics.drop', { lost: formatCount(step.lost), pct: formatPercent(step.drop_off) }) }}
      </span>
    </li>
  </ol>
</template>
