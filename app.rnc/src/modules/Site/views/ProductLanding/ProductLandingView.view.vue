<script setup lang="ts">
import { computed, nextTick, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { DEMO_FIRM_SLUG, ROOT_DOMAIN } from '@Constants';
import { vReveal } from '@Directives';
import { firmSiteUrl } from '@Helpers';
import { scrollToSection, usePublicFirm, useSeo, useTracking } from '../../composables';
import { PRODUCT_ANCHORS } from '../../constants';
import { TRACKING_EVENT_TYPE } from '../../enums';

/*
 * The product's own site on the root (rnc-4 R28, O7): the landing an accounting firm is sent to — what
 * the hub does for a firm, why now (the CBS on 01/01/2027, dated and sourced), the module menu, how
 * the beta works and the form that joins it (R29), the FAQ, and the demo firm's site (D24). The
 * product's name is the API's (`platform.name`, the one config value); no firm's name appears. The
 * `/` route picks this view when the host firm is the platform firm (`loadHomeView`); the diagnostic
 * is never offered here.
 */
const { firm, load } = usePublicFirm();
const route = useRoute();
const { t } = useI18n();
const { track } = useTracking();

const ANCHORS = Object.values(PRODUCT_ANCHORS) as string[];
const DEMO_URL = firmSiteUrl(DEMO_FIRM_SLUG, window.location, ROOT_DOMAIN);

const productName = computed(() => firm.value?.platform?.name || firm.value?.name || '');

const seo = computed(() => ({
  description: t('site.product.seo.description'),
  siteName: productName.value || null,
  title: t('site.product.seo.title'),
}));

useSeo(seo);

watch(() => route.hash, onHashChange);

onMounted(onComponentMount);

// HELPERS
function scrollToHash() {
  const ANCHOR = route.hash.slice(1);

  if (!ANCHORS.includes(ANCHOR)) return;

  nextTick(() => scrollToSection(ANCHOR));
}

// EVENTS
function onBetaClick() {
  track(TRACKING_EVENT_TYPE.CTA);

  scrollToSection(PRODUCT_ANCHORS.beta);
}

function onComponentMount() {
  load();

  track(TRACKING_EVENT_TYPE.VISIT);

  scrollToHash();
}

function onHashChange() {
  scrollToHash();
}
</script>

<template>
  <ThePublicLayout>
    <div
      class="flex flex-col"
      data-testid="product-landing"
    >
      <OProductHero
        :beta-anchor="PRODUCT_ANCHORS.beta"
        :demo-url="DEMO_URL"
        :product-name
        @on-beta-click="onBetaClick"
      />

      <OProductUrgency />

      <OProductHowItWorks />

      <OProductModules />

      <OProductBeta />

      <OProductFaq :product-name />

      <section class="mx-auto w-full max-w-6xl px-5 py-20 lg:py-24">
        <div
          v-reveal
          class="bg-primary-500 from-primary-500 to-primary-900 text-primary-contrast shadow-lift-lg relative flex flex-col items-start gap-6 overflow-hidden rounded-xl bg-linear-to-r p-8 sm:p-10 lg:flex-row lg:items-center lg:justify-between lg:p-12"
        >
          <div
            aria-hidden="true"
            class="from-primary-300/30 pointer-events-none absolute -top-32 -right-16 size-[28rem] rounded-full bg-radial to-transparent to-70% blur-3xl"
          />

          <div class="relative flex flex-col gap-2">
            <h2 class="text-2xl font-bold tracking-tight text-balance sm:text-3xl lg:text-4xl">
              {{ $t('site.product.closing.title') }}
            </h2>

            <p class="text-primary-100 text-base sm:text-lg">
              {{ $t('site.product.closing.text') }}
            </p>
          </div>

          <div class="relative flex shrink-0 flex-col gap-3 sm:flex-row">
            <a
              class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick shadow-primary-950/30 inline-flex items-center justify-center rounded-md px-6 py-3.5 text-base font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2"
              data-testid="product-closing-cta"
              :href="`#${PRODUCT_ANCHORS.beta}`"
              @click.prevent="onBetaClick"
            >
              {{ $t('site.product.cta.join') }}
            </a>

            <a
              v-if="DEMO_URL"
              class="touch-target border-primary-300/50 hover:border-primary-300/80 hover:bg-primary-950/30 motion-quick inline-flex items-center justify-center rounded-md border px-6 py-3.5 text-base font-semibold transition"
              rel="noopener"
              target="_blank"
              :href="DEMO_URL"
            >
              {{ $t('site.product.cta.demo') }}
            </a>
          </div>
        </div>
      </section>
    </div>
  </ThePublicLayout>
</template>
