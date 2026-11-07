<script setup lang="ts">
import type { SectorPageSummary } from '../../../interfaces';
import { vReveal, vSpotlight } from '@Directives';
import { SITE_ROUTE_NAMES } from '../../../constants';

defineProps<{
  sectors: SectorPageSummary[];
}>();
</script>

<template>
  <ul
    v-reveal.stagger
    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
  >
    <li
      v-for="sector in sectors"
      :key="sector.slug"
    >
      <RouterLink
        v-spotlight
        class="group bg-panel shadow-lift hover:shadow-lift-lg focus-visible:outline-primary-500 motion-quick border-primary-300 flex h-full flex-col gap-3 rounded-lg border-l-4 p-5 transition hover:-translate-y-1 focus-visible:outline-2"
        data-testid="sector-link"
        :data-sector="sector.slug"
        :to="{ name: SITE_ROUTE_NAMES.sector, params: { slug: sector.slug } }"
      >
        <strong class="text-heading text-lg font-semibold">
          {{ sector.label }}
        </strong>

        <span class="text-body flex-1 text-sm leading-relaxed">
          {{ sector.lede }}
        </span>

        <span
          class="text-muted group-hover:text-heading motion-quick inline-flex items-center gap-2 text-sm font-semibold transition"
        >
          {{ sector.title }}

          <i
            aria-hidden="true"
            class="pi pi-arrow-right text-subtle motion-quick text-xs transition group-hover:translate-x-1"
          />
        </span>
      </RouterLink>
    </li>
  </ul>
</template>
