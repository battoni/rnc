<script setup lang="ts">
import { computed } from 'vue';
import type { RepricingSpreadBand } from '../../../interfaces';

/*
 * How many listed clients fall in each band of suggested uplift (rnc-1 R26, the demo's
 * `rpSpread`): one bar per band, against the fullest band. The count is the number read out; the
 * bar is drawn for the eye and hidden from a screen reader.
 */
interface Props {
  bands: RepricingSpreadBand[];
}

const props = defineProps<Props>();

const top = computed(() => Math.max(1, ...props.bands.map((band) => band.count)));

// HELPERS
function widthOf(count: number): string {
  if (count === 0) return '0%';

  return `${Math.max(2, (count / top.value) * 100).toFixed(1)}%`;
}
</script>

<template>
  <ul
    class="flex flex-col gap-2.5"
    :aria-label="$t('repricing.spread.title')"
  >
    <li
      v-for="band in bands"
      class="grid grid-cols-[4.5rem_minmax(0,1fr)_3rem] items-center gap-3"
      data-testid="spread-band"
      :key="band.from"
      :data-count="band.count"
    >
      <span class="text-muted text-xs font-medium tabular-nums">
        {{ $t('repricing.spread.band', { from: band.from, to: band.to }) }}
      </span>

      <span
        aria-hidden="true"
        class="bg-panel-muted h-2 overflow-hidden rounded-sm"
      >
        <span
          class="bg-primary-500 motion-settle block h-full rounded-sm transition-[width]"
          :style="{ width: widthOf(band.count) }"
        />
      </span>

      <strong class="text-heading text-right text-sm font-semibold tabular-nums">{{ band.count }}</strong>
    </li>
  </ul>
</template>
