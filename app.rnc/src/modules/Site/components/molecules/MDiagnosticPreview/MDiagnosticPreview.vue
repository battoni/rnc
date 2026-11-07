<script setup lang="ts">
import { vCountUp, vTilt } from '@Directives';

/*
 * The hero's picture of the product: what the diagnostic returns, for one invented company (the
 * demo's "Atlas Soluções em TI"). Decorative — `aria-hidden`, and labelled "Exemplo do resultado" so
 * nobody reads it as their own numbers.
 *
 * It assembles rather than pops (`motion-enter` with indices picking up after the hero's words:
 * the card, then its rows, then the two chips), floats once in place (`motion-float`), leans
 * toward the pointer (`v-tilt`) and rolls its count up (`v-count-up`).
 */
defineProps<{
  /** The entrance index the card starts from; the hero passes where its own words end. */
  enterIndex: number;
}>();

const ROWS = [
  { isRisk: false, key: 'credit' },
  { isRisk: false, key: 'simples' },
  { isRisk: true, key: 'split' },
] as const;
</script>

<template>
  <div
    v-tilt="4"
    aria-hidden="true"
    class="relative mx-auto w-full max-w-md pt-4 pb-10 lg:max-w-none"
  >
    <div
      class="motion-enter"
      :style="{ '--reveal-index': enterIndex }"
    >
      <div class="motion-float">
        <div class="bg-panel text-body shadow-lift-lg ring-line/60 flex flex-col gap-4 rounded-xl p-5 ring-1 sm:p-6">
          <div
            class="motion-enter flex items-start justify-between gap-3"
            :style="{ '--reveal-index': enterIndex + 1 }"
          >
            <div class="flex min-w-0 flex-col gap-1">
              <span class="text-muted text-xs font-semibold tracking-wide uppercase">
                {{ $t('site.landing.preview.example') }}
              </span>

              <strong class="text-heading truncate">
                {{ $t('site.landing.preview.company') }}
              </strong>

              <span class="text-muted text-xs">
                {{ $t('site.landing.preview.profile') }}
              </span>
            </div>

            <span
              class="bg-success-100 text-success-800 inline-flex shrink-0 items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-semibold"
            >
              <i class="bg-success-600 size-1.5 rounded-full" />

              {{ $t('site.landing.preview.badge') }}
            </span>
          </div>

          <p
            class="motion-enter border-line flex items-baseline gap-2 border-b pb-4"
            :style="{ '--reveal-index': enterIndex + 2 }"
          >
            <strong
              v-count-up
              class="text-brand-word text-5xl font-bold tracking-tight tabular-nums"
              >7</strong
            >

            <span class="text-muted text-sm">
              {{ $t('site.landing.preview.count') }}
            </span>
          </p>

          <ul class="divide-line flex flex-col divide-y">
            <li
              v-for="(row, index) in ROWS"
              class="motion-enter flex items-start gap-3 py-3"
              :key="row.key"
              :style="{ '--reveal-index': enterIndex + 3 + index }"
            >
              <i :class="['mt-1.5 size-2 shrink-0 rounded-full', row.isRisk ? 'bg-warn-500' : 'bg-primary-300']" />

              <span class="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                <b class="text-muted text-xs font-semibold tracking-wide uppercase">
                  {{ row.isRisk ? $t('site.landing.preview.risk') : $t('site.landing.preview.opportunity') }}
                </b>

                {{ $t(`site.landing.preview.rows.${row.key}.title`) }}
              </span>

              <em class="text-heading shrink-0 text-right text-xs font-semibold not-italic">
                {{ $t(`site.landing.preview.rows.${row.key}.value`) }}
              </em>
            </li>
          </ul>

          <div
            class="motion-enter bg-panel-muted flex flex-wrap items-center justify-between gap-3 rounded-lg p-3 text-xs"
            :style="{ '--reveal-index': enterIndex + 6 }"
          >
            <span class="text-muted">
              {{ $t('site.landing.preview.foot') }}
            </span>

            <span class="bg-primary-500 text-primary-contrast rounded-md px-3 py-1.5 font-semibold">
              {{ $t('site.landing.preview.action') }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div
      class="motion-enter bg-panel/95 text-heading shadow-lift ring-line/60 absolute top-0 right-3 hidden items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold ring-1 backdrop-blur sm:flex"
      :style="{ '--reveal-index': enterIndex + 8 }"
    >
      <i class="bg-success-500 size-2 rounded-full" />

      {{ $t('site.landing.preview.scan') }}
    </div>

    <div
      class="motion-enter bg-panel/95 shadow-lift ring-line/60 absolute bottom-0 left-3 flex items-center gap-3 rounded-lg px-3 py-2 text-xs ring-1 backdrop-blur"
      :style="{ '--reveal-index': enterIndex + 10 }"
    >
      <span class="bg-primary-500 text-primary-contrast flex size-8 items-center justify-center rounded-full font-bold"
        >MT</span
      >

      <span class="flex flex-col">
        <strong class="text-heading">
          {{ $t('site.landing.preview.meeting') }}
        </strong>

        <span class="text-muted">
          {{ $t('site.landing.preview.meetingWhen') }}
        </span>
      </span>
    </div>
  </div>
</template>
