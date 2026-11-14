<script setup lang="ts">
import { vReveal, vSpotlight } from '@Directives';
import { PRODUCT_ANCHORS, PRODUCT_STEPS } from '../../../constants';

/*
 * What the hub does for a firm (rnc-4 R28), in the order a lead walks it: the free diagnostic its
 * clients take, the meeting they book with the firm's specialists, the card on the firm's pipeline and
 * the metrics of what brings clients in. Each step says what the FIRM gets, not what the software is.
 */
</script>

<template>
  <section
    :id="PRODUCT_ANCHORS.howItWorks"
    class="mx-auto flex w-full max-w-6xl scroll-mt-24 flex-col gap-10 px-5 py-20 lg:py-24"
    data-testid="product-how-it-works"
  >
    <header
      v-reveal
      class="flex flex-col gap-3"
    >
      <p
        class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
      >
        {{ $t('site.product.steps.kicker') }}
      </p>

      <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
        {{ $t('site.product.steps.title') }}
      </h2>

      <p class="text-body text-base leading-relaxed sm:text-lg">
        {{ $t('site.product.steps.lede') }}
      </p>
    </header>

    <ol
      v-reveal.stagger
      class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
    >
      <li
        v-for="(step, index) in PRODUCT_STEPS"
        v-spotlight
        class="bg-panel shadow-lift hover:shadow-lift-lg motion-quick relative flex flex-col gap-3 rounded-lg p-5 transition hover:-translate-y-1"
        data-testid="product-step"
        :key="step.key"
        :data-step="step.key"
      >
        <span class="flex items-center justify-between gap-3">
          <span class="bg-primary-100 text-primary-950 flex size-10 items-center justify-center rounded-full text-base">
            <i
              aria-hidden="true"
              :class="step.icon"
            />
          </span>

          <span class="text-muted text-xs font-bold tabular-nums">
            {{ String(index + 1).padStart(2, '0') }}
          </span>
        </span>

        <strong class="text-heading text-lg font-semibold">
          {{ $t(`site.product.steps.items.${step.key}.title`) }}
        </strong>

        <span class="text-body text-sm leading-relaxed">
          {{ $t(`site.product.steps.items.${step.key}.text`) }}
        </span>
      </li>
    </ol>
  </section>
</template>
