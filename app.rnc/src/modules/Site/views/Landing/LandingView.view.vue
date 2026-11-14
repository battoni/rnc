<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { SectorPageSummary } from '../../interfaces';
import { vCountUp, vReveal, vSpotlight } from '@Directives';
import { cbsKey, usePublicFirm, useSeo, useTracking } from '../../composables';
import { SECTOR_GRID_ANCHOR, SITE_ROUTE_NAMES, WHAT_CHANGES } from '../../constants';
import { TRACKING_EVENT_TYPE } from '../../enums';
import { GetSectorPagesService } from '../../services';

const { firm, isBrandPending, load } = usePublicFirm();
const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const { track } = useTracking();

// The CBS's date as still ahead, or as passed — on the countdown's own condition (rnc-4 verify P2-S2).
const TITLE_KEY = cbsKey('site.landing.title');

const hasFailed = ref(false);
const isLoading = ref(true);
const sectors = ref<SectorPageSummary[]>([]);

// The hero assembles on mount, one `--motion-stagger` apart: the kicker, then the headline word by
// word (`AWordRise`), then the lede, the buttons, the countdown and the picture of the product.
/*
 * The kicker is the brand's tagline when it has one (rnc-3 R3), else the site's own; the proof strip is
 * the firm's — or the sub-brand's — four points (`proof`, rnc-4 F2a), and a firm with none shows no strip
 * (rnc-4 R15: the build's own four were one firm's). Both wait unseen (`invisible`, not `opacity-0`: the
 * entrance animation drives the opacity) while a brand is pending (W38, review S1).
 */
const kicker = computed(() => firm.value?.tagline ?? t('site.landing.kicker'));
const titleWordCount = computed(() => t(TITLE_KEY).split(' ').length);
const wordCount = computed(() => titleWordCount.value + t('site.landing.ask').split(' ').length);

const proofPoints = computed(() =>
  (firm.value?.proof ?? []).map((point) => ({
    figure: point.value,
    key: `${point.value}-${point.label}`,
    label: point.label,
  }))
);

const seo = computed(() => ({
  description: t(cbsKey('site.seo.landingDescription')),
  siteName: firm.value?.name ?? null,
  title: t('site.seo.landingTitle'),
}));

useSeo(seo);

watch(() => route.hash, onHashChange);

onMounted(onComponentMount);

// HELPERS
function scrollToAnchor() {
  if (route.hash !== `#${SECTOR_GRID_ANCHOR}`) return;

  nextTick(() => document.getElementById(SECTOR_GRID_ANCHOR)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}

// EVENTS
function fetchSectors() {
  isLoading.value = true;
  hasFailed.value = false;

  GetSectorPagesService()
    .then(({ data }) => (sectors.value = data))
    .catch(() => (hasFailed.value = true))
    .finally(onSectorsSettled);
}

function onComponentMount() {
  load();

  track(TRACKING_EVENT_TYPE.VISIT);

  fetchSectors();
}

function onDiagnosticClick() {
  track(TRACKING_EVENT_TYPE.CTA);

  push({ name: SITE_ROUTE_NAMES.diagnostic });
}

function onHashChange() {
  scrollToAnchor();
}

function onSectorsSettled() {
  isLoading.value = false;

  scrollToAnchor();
}
</script>

<template>
  <ThePublicLayout>
    <div
      class="flex flex-col"
      data-testid="landing"
    >
      <section
        class="from-primary-500 via-primary-600 to-primary-900 text-primary-contrast relative overflow-hidden bg-linear-to-br"
      >
        <!-- Atmosphere: a cyan glow where the picture of the product sits, a deeper navy pool at the
             opposite corner, and a faint dot grid so the band reads as a surface rather than a fill. -->
        <div
          aria-hidden="true"
          class="pointer-events-none absolute inset-0"
        >
          <div
            class="from-primary-300/35 absolute -top-48 -right-24 size-[44rem] rounded-full bg-radial to-transparent to-70% blur-3xl"
          />

          <div
            class="from-primary-950/60 absolute -bottom-56 -left-32 size-[40rem] rounded-full bg-radial to-transparent to-70% blur-3xl"
          />

          <div
            class="absolute inset-0 [background-image:radial-gradient(var(--color-primary-100)_1px,transparent_1px)] bg-size-[28px_28px] opacity-[0.07]"
          />
        </div>

        <!-- 1.1fr/0.9fr, not 1.05/1: at 1440 the copy column was 537px, the two buttons wrapped onto two
             rows and the countdown — the hook — landed at 779px, under a 749px fold. At 598px they share a
             row and the lede loses a line; at 490px the card's rows still hold on one line. -->
        <div
          class="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-5 py-14 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:gap-16 lg:py-20"
        >
          <div class="flex min-w-0 flex-col gap-6">
            <p
              data-testid="hero-kicker"
              :class="[
                'motion-enter text-primary-200 inline-flex items-center gap-2 text-xs font-semibold tracking-widest uppercase',
                { invisible: isBrandPending },
              ]"
            >
              <span
                aria-hidden="true"
                class="bg-primary-300 shadow-primary-300/60 size-2 rounded-full shadow-[0_0_0_4px]"
              />

              {{ kicker }}
            </p>

            <!-- Semibold, not bold: at 60px Space Grotesk's 700 reads as shouting; 600 is the demo's weight. -->
            <h1 class="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
              <AWordRise
                class="block"
                :start-index="1"
                :text="$t(TITLE_KEY)"
              />

              <AWordRise
                class="text-primary-300 mt-3 block text-[0.62em] leading-[1.15] font-semibold"
                :start-index="titleWordCount + 1"
                :text="$t('site.landing.ask')"
              />
            </h1>

            <p
              class="motion-enter text-primary-100 text-base leading-relaxed sm:text-lg"
              :style="{ '--reveal-index': wordCount + 1 }"
            >
              {{ $t('site.landing.lede') }}
            </p>

            <div
              class="motion-enter flex flex-col gap-3 sm:flex-row sm:flex-wrap"
              :style="{ '--reveal-index': wordCount + 2 }"
            >
              <button
                class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick shadow-primary-950/30 inline-flex items-center justify-center gap-2 rounded-md px-6 py-3.5 text-base font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2"
                data-testid="hero-cta"
                type="button"
                @click="onDiagnosticClick"
              >
                {{ $t('site.cta.primary') }}

                <i
                  aria-hidden="true"
                  class="pi pi-arrow-right text-sm"
                />
              </button>

              <RouterLink
                class="touch-target border-primary-300/40 hover:border-primary-300/70 hover:bg-primary-700/60 motion-quick inline-flex items-center justify-center rounded-md border px-6 py-3.5 text-base font-semibold transition"
                exact-active-class=""
                :to="{ name: SITE_ROUTE_NAMES.home, hash: `#${SECTOR_GRID_ANCHOR}` }"
              >
                {{ $t('site.landing.sectorsLink') }}
              </RouterLink>
            </div>

            <MCbsCountdown
              class="motion-enter max-w-md"
              :style="{ '--reveal-index': wordCount + 3 }"
            />
          </div>

          <MDiagnosticPreview :enter-index="4" />
        </div>
      </section>

      <section
        v-if="proofPoints.length"
        class="relative z-10 mx-auto -mt-10 w-full max-w-6xl px-5"
      >
        <ul
          v-reveal.stagger
          data-testid="proof"
          :aria-label="$t('site.landing.proofLabel')"
          :class="[
            'bg-line shadow-lift-lg ring-line relative grid grid-cols-2 gap-px overflow-hidden rounded-xl ring-1 lg:grid-cols-4',
            { invisible: isBrandPending },
          ]"
        >
          <li
            v-for="point in proofPoints"
            class="bg-panel flex min-w-0 flex-col gap-1 p-5 sm:p-6"
            :key="point.key"
          >
            <strong
              v-count-up
              class="text-heading text-2xl font-bold tracking-tight break-words tabular-nums sm:text-3xl"
            >
              {{ point.figure }}
            </strong>

            <span class="text-muted text-sm">
              {{ point.label }}
            </span>
          </li>
        </ul>
      </section>

      <section class="mx-auto flex w-full max-w-6xl flex-col gap-10 px-5 py-20 lg:py-24">
        <header
          v-reveal
          class="flex flex-col gap-3"
        >
          <p
            class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
          >
            {{ $t('site.landing.changesKicker') }}
          </p>

          <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
            {{ $t('site.landing.changesTitle') }}
          </h2>
        </header>

        <ol
          v-reveal.stagger
          class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
          data-testid="what-changes"
        >
          <li
            v-for="(change, index) in WHAT_CHANGES"
            v-spotlight
            class="bg-panel shadow-lift hover:shadow-lift-lg motion-quick flex flex-col gap-3 rounded-lg p-5 transition hover:-translate-y-1"
            :key="change"
          >
            <span
              class="bg-primary-100 text-primary-950 flex size-8 items-center justify-center rounded-full text-xs font-bold tabular-nums"
            >
              {{ String(index + 1).padStart(2, '0') }}
            </span>

            <strong class="text-heading text-lg font-semibold">
              {{ $t(`site.landing.changes.${change}.title`) }}
            </strong>

            <span class="text-body text-sm leading-relaxed">
              {{ $t(`site.landing.changes.${change}.text`) }}
            </span>
          </li>
        </ol>
      </section>

      <section class="bg-panel-muted">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-10 px-5 py-20 lg:py-24">
          <header
            v-reveal
            class="flex flex-col gap-3"
          >
            <p
              class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
            >
              {{ $t('site.landing.timelineKicker') }}
            </p>

            <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
              {{ $t('site.landing.timelineTitle') }}
            </h2>

            <p class="text-muted flex flex-wrap items-center gap-4 text-sm">
              <span class="inline-flex items-center gap-2">
                <i class="bg-line-strong size-3 rounded-xs" />

                {{ $t('site.landing.timelineLegacy') }}
              </span>

              <span class="inline-flex items-center gap-2">
                <i class="bg-primary-300 size-3 rounded-xs" />

                {{ $t('site.landing.timelineNew') }}
              </span>
            </p>
          </header>

          <MReformaTimeline />

          <p
            v-reveal
            class="bg-panel text-body shadow-lift border-warn-500 flex flex-col gap-2 rounded-lg border-l-4 p-4 text-sm sm:flex-row sm:items-center sm:gap-3"
            data-testid="news"
          >
            <span class="bg-warn-100 text-warn-800 self-start rounded-sm px-2 py-1 text-xs font-semibold sm:self-auto">
              {{ $t('site.landing.newsChip') }}
            </span>

            {{ $t('site.landing.news') }}
          </p>
        </div>
      </section>

      <section
        :id="SECTOR_GRID_ANCHOR"
        class="mx-auto flex w-full max-w-6xl scroll-mt-20 flex-col gap-10 px-5 py-20 lg:py-24"
      >
        <header
          v-reveal
          class="flex flex-col gap-3"
        >
          <p
            class="text-muted before:bg-primary-300 flex items-center gap-3 text-xs font-semibold tracking-widest uppercase before:h-px before:w-8"
          >
            {{ $t('site.landing.sectorsKicker') }}
          </p>

          <h2 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
            {{ $t('site.landing.sectorsTitle') }}
          </h2>
        </header>

        <div
          v-if="isLoading"
          class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
          <Skeleton
            v-for="placeholder in 6"
            class="rounded-lg"
            height="10rem"
            :key="placeholder"
          />
        </div>

        <div
          v-else-if="hasFailed"
          class="bg-panel shadow-lift flex flex-col items-start gap-3 rounded-lg p-6"
          data-testid="sectors-failed"
        >
          <p class="text-body">
            {{ $t('site.landing.sectorsFailed') }}
          </p>

          <Button
            icon="pi pi-refresh"
            severity="secondary"
            :label="$t('site.landing.retry')"
            @click="fetchSectors"
          />
        </div>

        <MSectorGrid
          v-else
          :sectors
        />
      </section>

      <section class="mx-auto w-full max-w-6xl px-5 pb-20 lg:pb-24">
        <div
          v-reveal
          class="from-primary-500 to-primary-800 text-primary-contrast shadow-lift-lg relative flex flex-col items-start gap-6 overflow-hidden rounded-2xl bg-linear-to-r p-8 sm:p-10 lg:flex-row lg:items-center lg:justify-between lg:p-12"
        >
          <div
            aria-hidden="true"
            class="from-primary-300/30 pointer-events-none absolute -top-32 -right-16 size-[28rem] rounded-full bg-radial to-transparent to-70% blur-3xl"
          />

          <div class="relative flex flex-col gap-2">
            <h2 class="text-2xl font-bold tracking-tight text-balance sm:text-3xl lg:text-4xl">
              {{ $t('site.landing.closingTitle') }}
            </h2>

            <p class="text-primary-100 text-base sm:text-lg">
              {{ $t('site.landing.closingText') }}
            </p>
          </div>

          <button
            class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick shadow-primary-950/30 relative shrink-0 rounded-md px-6 py-3.5 text-base font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2"
            data-testid="closing-cta"
            type="button"
            @click="onDiagnosticClick"
          >
            {{ $t('site.cta.start') }}
          </button>
        </div>
      </section>
    </div>
  </ThePublicLayout>
</template>
