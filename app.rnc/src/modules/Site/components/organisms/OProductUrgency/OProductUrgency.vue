<script setup lang="ts">
import { vReveal, vSpotlight } from '@Directives';
import { cbsKey } from '../../../composables';
import { PRODUCT_FACTS } from '../../../constants';

/*
 * Why a firm should move now (rnc-4 R28): the three dates of the transition a firm's clients will
 * ask about, each dated and from the law (LC 214/2025, facts of rnc-1 §2), with the source, the day
 * the dates were checked and the CBS's requested delay, said as what it is — news, not law. The title has a
 * post-date twin (`cbsKey`, rnc-4 verify P2-S2).
 */
const TITLE_KEY = cbsKey('site.product.urgency.title');
</script>

<template>
  <section
    class="relative z-10 mx-auto -mt-10 w-full max-w-6xl px-5"
    data-testid="product-urgency"
  >
    <div class="bg-panel shadow-lift-lg ring-line flex flex-col gap-6 rounded-xl p-6 ring-1 sm:p-8">
      <header
        v-reveal
        class="flex flex-col gap-2"
      >
        <p
          class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
        >
          {{ $t('site.product.urgency.kicker') }}
        </p>

        <h2 class="text-heading text-2xl font-bold tracking-tight text-balance sm:text-3xl">
          {{ $t(TITLE_KEY) }}
        </h2>
      </header>

      <ol
        v-reveal.stagger
        class="grid gap-4 md:grid-cols-3"
      >
        <li
          v-for="fact in PRODUCT_FACTS"
          v-spotlight
          class="bg-panel-muted flex flex-col gap-2 rounded-lg p-5"
          data-testid="product-fact"
          :key="fact.key"
          :data-date="fact.date"
        >
          <strong class="text-heading text-xl font-bold tracking-tight tabular-nums">
            {{ fact.date }}
          </strong>

          <span class="text-body text-sm leading-relaxed">
            {{ $t(`site.product.urgency.facts.${fact.key}`) }}
          </span>
        </li>
      </ol>

      <div class="flex flex-col gap-3 text-sm lg:flex-row lg:items-center lg:justify-between">
        <p class="text-muted">
          {{ $t('site.product.urgency.source') }}
        </p>

        <p class="text-body flex flex-col gap-2 sm:flex-row sm:items-center">
          <span class="bg-warn-100 text-warn-800 self-start rounded-sm px-2 py-1 text-xs font-semibold sm:self-auto">
            {{ $t('site.landing.newsChip') }}
          </span>

          {{ $t('site.product.urgency.news') }}
        </p>
      </div>
    </div>
  </section>
</template>
