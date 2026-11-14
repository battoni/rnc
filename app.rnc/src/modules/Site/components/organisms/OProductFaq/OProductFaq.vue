<script setup lang="ts">
import { vReveal } from '@Directives';
import { PRODUCT_ANCHORS, PRODUCT_FAQ } from '../../../constants';

/*
 * What a firm asks before joining (rnc-4 R28): set-up, its clients' data, the CBS's date, what the
 * diagnostic is not, the price (D23's line) and who the beta is for. Native `<details>`: it opens with
 * the keyboard, a screen reader names its state, and the page works with no script at all.
 */
defineProps<{
  productName: string;
}>();
</script>

<template>
  <section
    :id="PRODUCT_ANCHORS.faq"
    class="bg-panel-muted scroll-mt-24"
    data-testid="product-faq"
  >
    <div
      class="mx-auto grid w-full max-w-6xl gap-10 px-5 py-20 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:py-24"
    >
      <header
        v-reveal
        class="flex flex-col gap-3"
      >
        <p
          class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
        >
          {{ $t('site.product.faq.kicker') }}
        </p>

        <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
          {{ $t('site.product.faq.title') }}
        </h2>
      </header>

      <div
        v-reveal.stagger
        class="flex flex-col gap-3"
      >
        <details
          v-for="question in PRODUCT_FAQ"
          class="group bg-panel shadow-lift rounded-lg"
          data-testid="product-faq-item"
          :key="question"
        >
          <summary
            class="touch-target text-heading flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg p-5 font-semibold [&::-webkit-details-marker]:hidden"
          >
            {{ $t(`site.product.faq.items.${question}.question`) }}

            <i
              aria-hidden="true"
              class="pi pi-plus text-subtle motion-quick shrink-0 transition-transform group-open:rotate-45"
            />
          </summary>

          <p class="text-body px-5 pb-5 text-sm leading-relaxed">
            {{ $t(`site.product.faq.items.${question}.answer`, { product: productName }) }}
          </p>
        </details>
      </div>
    </div>
  </section>
</template>
