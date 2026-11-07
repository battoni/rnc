<script setup lang="ts">
import { TRANSITION_YEARS } from '../../../constants';

const TENTHS = 10;
</script>

<template>
  <ol
    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
    data-testid="reforma-timeline"
  >
    <li
      v-for="entry in TRANSITION_YEARS"
      class="bg-panel shadow-lift flex flex-col gap-3 rounded-lg p-4"
      :key="entry.year"
      :data-year="entry.year"
    >
      <div class="flex items-baseline justify-between gap-3">
        <strong class="text-heading text-2xl font-bold tabular-nums">
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

      <div
        aria-hidden="true"
        class="bg-primary-300 flex h-2 overflow-hidden rounded-xs"
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
