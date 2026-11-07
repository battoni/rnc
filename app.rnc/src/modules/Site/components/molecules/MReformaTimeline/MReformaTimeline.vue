<script setup lang="ts">
import { vReveal } from '@Directives';
import { TRANSITION_YEARS } from '../../../constants';

const TENTHS = 10;
</script>

<template>
  <ol
    v-reveal.stagger
    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
    data-testid="reforma-timeline"
  >
    <li
      v-for="entry in TRANSITION_YEARS"
      class="bg-panel shadow-lift hover:shadow-lift-lg motion-quick flex flex-col gap-3 rounded-lg p-4 transition hover:-translate-y-0.5"
      :key="entry.year"
      :data-year="entry.year"
    >
      <div class="flex items-baseline justify-between gap-3">
        <strong class="text-heading text-2xl font-bold tracking-tight tabular-nums">
          {{ entry.year }}
        </strong>

        <span class="text-muted text-xs">
          {{
            entry.legacyTenths
              ? $t('site.landing.timelineShare', { tenths: entry.legacyTenths })
              : $t('site.landing.timelineGone')
          }}
        </span>
      </div>

      <!-- The cyan is the new tax growing in; the grey the old share still charged. The bar thickens
           from its baseline once the row is revealed — never along its length, which is the number. -->
      <div
        aria-hidden="true"
        class="motion-wipe-y bg-primary-300 flex h-2 overflow-hidden rounded-xs"
      >
        <span
          class="bg-line-strong h-full"
          :style="{ width: `${(entry.legacyTenths / TENTHS) * 100}%` }"
        />
      </div>

      <p class="text-body text-sm leading-relaxed">
        {{ $t(`site.landing.years.y${entry.year}`) }}
      </p>
    </li>
  </ol>
</template>
