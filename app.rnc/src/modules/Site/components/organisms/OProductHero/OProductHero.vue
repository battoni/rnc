<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { vTilt } from '@Directives';

/*
 * The product landing's first screen (rnc-4 R28): who it is for, the product's name from the API, the
 * question every firm's clients will ask, the beta CTA and the demo firm's site, and the CBS
 * countdown — the hook — in the first screen. The picture is an invented firm's pipeline, labelled as
 * an example and `aria-hidden`, assembled after the words like the firm landing's diagnostic card.
 */
const emit = defineEmits<{
  onBetaClick: [];
}>();

const props = defineProps<{
  betaAnchor: string;
  demoUrl: string | null;
  productName: string;
}>();

const { t } = useI18n();

const PREVIEW_CARDS = [
  { key: 'bakery', stage: 'lead' },
  { key: 'clinic', stage: 'meeting' },
  { key: 'software', stage: 'proposal' },
] as const;
const PREVIEW_METRICS = ['diagnostics', 'meetings', 'proposals'] as const;

const titleWordCount = computed(() => t('site.product.hero.title').split(' ').length);
const wordCount = computed(() => titleWordCount.value + t('site.product.hero.ask').split(' ').length);

// EVENTS
function onBetaClick() {
  emit('onBetaClick');
}
</script>

<template>
  <section
    class="bg-primary-500 from-primary-500 via-primary-700 to-primary-950 text-primary-contrast relative overflow-hidden bg-linear-to-br"
    data-testid="product-hero"
  >
    <!-- Atmosphere: the accent's glow behind the picture, a deep pool opposite, a faint dot grid. The band's
         own colour is the ramp's 500, the lightest stop of the gradient: what a measurement reads as its ground. -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0"
    >
      <div
        class="from-primary-300/30 absolute -top-48 -right-24 size-[44rem] rounded-full bg-radial to-transparent to-70% blur-3xl"
      />

      <div
        class="from-primary-950/70 absolute -bottom-56 -left-32 size-[40rem] rounded-full bg-radial to-transparent to-70% blur-3xl"
      />

      <div
        class="absolute inset-0 [background-image:radial-gradient(var(--color-primary-100)_1px,transparent_1px)] bg-size-[28px_28px] opacity-[0.07]"
      />
    </div>

    <div
      class="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-5 py-14 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:gap-16 lg:py-20"
    >
      <div class="flex min-w-0 flex-col gap-6">
        <p
          class="motion-enter text-primary-100 inline-flex flex-wrap items-center gap-x-3 gap-y-2 text-xs font-semibold tracking-widest uppercase"
        >
          <span
            class="bg-primary-950/40 ring-primary-300/40 inline-flex items-center gap-2 rounded-sm px-2 py-1 ring-1"
            data-testid="product-name"
          >
            <span
              aria-hidden="true"
              class="bg-primary-300 shadow-primary-300/60 size-2 rounded-full shadow-[0_0_0_4px]"
            />

            {{ props.productName }}
          </span>

          {{ $t('site.product.hero.kicker') }}
        </p>

        <h1 class="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
          <AWordRise
            class="block"
            :start-index="1"
            :text="$t('site.product.hero.title')"
          />

          <AWordRise
            class="text-primary-200 mt-3 block text-[0.62em] leading-[1.15] font-semibold"
            :start-index="titleWordCount + 1"
            :text="$t('site.product.hero.ask')"
          />
        </h1>

        <p
          class="motion-enter text-primary-100 text-base leading-relaxed sm:text-lg"
          :style="{ '--reveal-index': wordCount + 1 }"
        >
          {{ $t('site.product.hero.lede', { product: props.productName }) }}
        </p>

        <div
          class="motion-enter flex flex-col gap-3 sm:flex-row sm:flex-wrap"
          :style="{ '--reveal-index': wordCount + 2 }"
        >
          <a
            class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick shadow-primary-950/30 inline-flex items-center justify-center gap-2 rounded-md px-6 py-3.5 text-base font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2"
            data-testid="product-beta-cta"
            :href="`#${props.betaAnchor}`"
            @click.prevent="onBetaClick"
          >
            {{ $t('site.product.cta.join') }}

            <i
              aria-hidden="true"
              class="pi pi-arrow-right text-sm"
            />
          </a>

          <a
            v-if="props.demoUrl"
            class="touch-target border-primary-300/50 hover:border-primary-300/80 hover:bg-primary-950/30 motion-quick inline-flex items-center justify-center gap-2 rounded-md border px-6 py-3.5 text-base font-semibold transition"
            data-testid="product-demo-link"
            rel="noopener"
            target="_blank"
            :href="props.demoUrl"
          >
            {{ $t('site.product.cta.demo') }}

            <i
              aria-hidden="true"
              class="pi pi-external-link text-sm"
            />
          </a>
        </div>

        <MCbsCountdown
          class="motion-enter max-w-md"
          :style="{ '--reveal-index': wordCount + 3 }"
        />
      </div>

      <div
        v-tilt="4"
        aria-hidden="true"
        class="relative mx-auto w-full max-w-md pt-4 pb-8 lg:max-w-none"
      >
        <div
          class="motion-enter"
          :style="{ '--reveal-index': 4 }"
        >
          <div class="motion-float">
            <div class="bg-panel text-body shadow-lift-lg ring-line/60 flex flex-col gap-4 rounded-xl p-5 ring-1">
              <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-1">
                  <span class="text-muted text-xs font-semibold tracking-wide uppercase">
                    {{ $t('site.product.hero.preview.example') }}
                  </span>

                  <strong class="text-heading truncate">
                    {{ $t('site.product.hero.preview.firm') }}
                  </strong>
                </div>

                <span
                  class="bg-success-100 text-success-800 inline-flex shrink-0 items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-semibold"
                >
                  <i class="bg-success-600 size-1.5 rounded-full" />

                  {{ $t('site.product.hero.preview.live') }}
                </span>
              </div>

              <ul class="flex flex-col gap-2.5">
                <li
                  v-for="(card, index) in PREVIEW_CARDS"
                  class="motion-enter bg-panel-muted flex items-center gap-3 rounded-lg p-3"
                  :key="card.key"
                  :style="{ '--reveal-index': 5 + index }"
                >
                  <span
                    class="bg-primary-500 text-primary-contrast flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                  >
                    {{ $t(`site.product.hero.preview.cards.${card.key}.initials`) }}
                  </span>

                  <span class="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                    <strong class="text-heading truncate">
                      {{ $t(`site.product.hero.preview.cards.${card.key}.company`) }}
                    </strong>

                    <span class="text-muted truncate text-xs">
                      {{ $t(`site.product.hero.preview.cards.${card.key}.detail`) }}
                    </span>
                  </span>

                  <span class="bg-panel text-heading shrink-0 rounded-sm px-2 py-1 text-xs font-semibold">
                    {{ $t(`site.product.hero.preview.stages.${card.stage}`) }}
                  </span>
                </li>
              </ul>

              <div class="border-line grid grid-cols-3 gap-2 border-t pt-4 text-center">
                <div
                  v-for="metric in PREVIEW_METRICS"
                  class="flex flex-col gap-0.5"
                  :key="metric"
                >
                  <strong class="text-heading text-xl font-bold tabular-nums">
                    {{ $t(`site.product.hero.preview.metrics.${metric}.value`) }}
                  </strong>

                  <span class="text-muted text-xs">
                    {{ $t(`site.product.hero.preview.metrics.${metric}.label`) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div
          class="motion-enter bg-panel/95 text-heading shadow-lift ring-line/60 absolute bottom-0 left-3 flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold ring-1 backdrop-blur"
          :style="{ '--reveal-index': 10 }"
        >
          <i class="pi pi-bell text-primary-500" />

          {{ $t('site.product.hero.preview.bell') }}
        </div>
      </div>
    </div>
  </section>
</template>
