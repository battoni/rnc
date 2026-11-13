<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DiagnosticSimulation } from '../../../interfaces';
import { TAX_SWATCH_CLASSES } from '../../../constants';

/*
 * The impact simulator (rnc-1 R14): one stacked bar per year, 2026–2033, of the reference rates on
 * revenue the API computes (the demo's `taxMix()`), the legend, and the split payment's monthly cash
 * effect. Drawn in HTML rather than SVG so its labels keep their 12 px on a phone — an SVG scaled to
 * a 390 px column shrinks its text with it.
 */
const props = defineProps<{
  regime: string | null;
  simulation: DiagnosticSimulation;
}>();

const { locale } = useI18n();

/** The top of the scale, in percentage points: the highest bar (≈ 26,5 %) stays under it. */
const MAX_RATE = 32;
const SIMPLES = 'simples';

const isSimples = computed(() => props.regime === SIMPLES);

const chartLabel = computed(() =>
  props.simulation.years.map((year) => `${year.year} ${formatRate(year.total)}`).join(', ')
);

const first = computed(() => props.simulation.years[0]);
const last = computed(() => props.simulation.years[props.simulation.years.length - 1]);

// HELPERS
function formatRate(rate: number): string {
  return `${rate.toLocaleString(locale.value, { maximumFractionDigits: 1, minimumFractionDigits: 1 })}%`;
}

function share(rate: number): string {
  return `${(rate / MAX_RATE) * 100}%`;
}
</script>

<template>
  <section
    class="bg-panel shadow-lift flex flex-col gap-5 rounded-xl p-5 sm:p-6"
    data-testid="simulator"
  >
    <div class="flex flex-col gap-2">
      <h3 class="text-heading text-xl font-semibold">
        {{ $t('diagnostic.simulator.title') }}
      </h3>

      <p class="text-muted text-sm">
        {{
          $t('diagnostic.simulator.lede', {
            first: first ? formatRate(first.total) : '',
            last: last ? formatRate(last.total) : '',
          })
        }}
      </p>
    </div>

    <div
      class="flex flex-col gap-2"
      role="img"
      :aria-label="$t('diagnostic.simulator.chartLabel', { years: chartLabel })"
    >
      <div class="border-line-strong grid h-56 grid-cols-8 items-end gap-0.5 border-b sm:gap-4">
        <div
          v-for="(year, index) in props.simulation.years"
          class="flex h-full min-w-0 flex-col items-center justify-end gap-1"
          data-testid="sim-year"
          :key="year.year"
          :data-year="year.year"
        >
          <span class="text-heading text-xs font-semibold tracking-tight tabular-nums">
            {{ formatRate(year.total) }}
          </span>

          <!-- The column wipes in sideways (`motion-wipe-x`), never up: its height is the number, and a
               bar growing to it would show a false rate while it grows. -->
          <div
            class="motion-wipe-x flex w-full max-w-12 flex-col-reverse overflow-hidden rounded-t-sm"
            :style="{ '--reveal-index': index, height: share(year.total) }"
          >
            <span
              v-for="part in year.parts"
              :key="part.tax"
              :class="['block w-full', TAX_SWATCH_CLASSES[part.tax]]"
              :data-tax="part.tax"
              :style="{ height: `${(part.rate / year.total) * 100}%` }"
              :title="`${year.year} · ${part.label}: ${formatRate(part.rate)} (${$t('diagnostic.result.estimate')})`"
            />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-8 gap-0.5 sm:gap-4">
        <span
          v-for="year in props.simulation.years"
          class="text-muted text-center text-xs tabular-nums"
          :key="year.year"
        >
          {{ year.year }}
        </span>
      </div>
    </div>

    <ul
      class="flex flex-wrap gap-x-5 gap-y-2 text-sm"
      data-testid="sim-legend"
    >
      <li
        v-for="tax in props.simulation.taxes"
        class="text-body flex items-center gap-2"
        :key="tax.tax"
      >
        <span :class="['size-3 rounded-xs', TAX_SWATCH_CLASSES[tax.tax]]" />

        {{ tax.label }}

        <span class="text-muted text-xs">
          {{ tax.leaving ? $t('diagnostic.simulator.leaving') : $t('diagnostic.simulator.coming') }}
        </span>
      </li>
    </ul>

    <p
      v-if="isSimples"
      class="text-muted text-sm"
      data-testid="simples-note"
    >
      {{ $t('diagnostic.simulator.simplesNote') }}
    </p>

    <div
      class="bg-panel-muted flex flex-col gap-1 rounded-lg p-4"
      data-testid="split-payment"
    >
      <strong class="text-heading text-sm">
        {{ $t('diagnostic.simulator.splitTitle') }}
      </strong>

      <span class="text-heading text-lg font-semibold tabular-nums">
        {{ props.simulation.split_payment.label }}

        <span class="text-muted text-xs font-normal">· {{ $t('diagnostic.result.estimate') }}</span>
      </span>

      <span class="text-muted text-sm">
        {{ $t('diagnostic.simulator.splitText') }}
      </span>
    </div>
  </section>
</template>
