<script setup lang="ts">
import { vReveal, vSpotlight } from '@Directives';
import { PRODUCT_ANCHORS, PRODUCT_CORE_MODULES, PRODUCT_OPT_IN_MODULES } from '../../../constants';

/*
 * The module menu (rnc-4 R28, R17): the core every firm has, always on, and the opt-ins a firm
 * switches on when it uses them — the names are the console's (`platform.modules.names`), the
 * sentences say what each does for the firm. The network channel is one network's and is not offered.
 */
</script>

<template>
  <section
    :id="PRODUCT_ANCHORS.modules"
    class="bg-panel-muted scroll-mt-24"
    data-testid="product-modules"
  >
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-10 px-5 py-20 lg:py-24">
      <header
        v-reveal
        class="flex flex-col gap-3"
      >
        <p
          class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
        >
          {{ $t('site.product.modules.kicker') }}
        </p>

        <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
          {{ $t('site.product.modules.title') }}
        </h2>

        <p class="text-body text-base leading-relaxed sm:text-lg">
          {{ $t('site.product.modules.lede') }}
        </p>
      </header>

      <div class="grid gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <div class="flex flex-col gap-4">
          <h3 class="text-heading flex items-center gap-2 text-lg font-semibold">
            <i
              aria-hidden="true"
              class="pi pi-check-circle text-primary-500"
            />

            {{ $t('site.product.modules.core') }}
          </h3>

          <ul
            v-reveal.stagger
            class="flex flex-col gap-3"
          >
            <li
              v-for="module in PRODUCT_CORE_MODULES"
              class="bg-panel shadow-lift flex flex-col gap-1 rounded-lg p-4"
              data-testid="product-module-core"
              :key="module"
              :data-module="module"
            >
              <strong class="text-heading font-semibold">
                {{ $t(`platform.modules.names.${module}`) }}
              </strong>

              <span class="text-body text-sm leading-relaxed">
                {{ $t(`site.product.modules.items.${module}`) }}
              </span>
            </li>
          </ul>
        </div>

        <div class="flex flex-col gap-4">
          <h3 class="text-heading flex items-center gap-2 text-lg font-semibold">
            <i
              aria-hidden="true"
              class="pi pi-plus-circle text-primary-500"
            />

            {{ $t('site.product.modules.optIn') }}
          </h3>

          <ul
            v-reveal.stagger
            class="grid gap-3 sm:grid-cols-2"
          >
            <li
              v-for="module in PRODUCT_OPT_IN_MODULES"
              v-spotlight
              class="bg-panel shadow-lift hover:shadow-lift-lg motion-quick flex flex-col gap-1 rounded-lg p-4 transition"
              data-testid="product-module-optin"
              :key="module"
              :data-module="module"
            >
              <strong class="text-heading font-semibold">
                {{ $t(`platform.modules.names.${module}`) }}
              </strong>

              <span class="text-body text-sm leading-relaxed">
                {{ $t(`site.product.modules.items.${module}`) }}
              </span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>
