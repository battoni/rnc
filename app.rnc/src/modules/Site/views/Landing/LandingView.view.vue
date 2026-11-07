<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { SectorPageSummary } from '../../interfaces';
import { usePublicFirm, useSeo, useTracking } from '../../composables';
import { PROOF_POINTS, SECTOR_GRID_ANCHOR, SITE_ROUTE_NAMES, WHAT_CHANGES } from '../../constants';
import { TRACKING_EVENT_TYPE } from '../../enums';
import { GetSectorPagesService } from '../../services';

const { firm, load } = usePublicFirm();
const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const { track } = useTracking();

const hasFailed = ref(false);
const isLoading = ref(true);
const sectors = ref<SectorPageSummary[]>([]);

const seo = computed(() => ({
  description: t('site.seo.landingDescription'),
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
        <div class="mx-auto grid w-full max-w-6xl items-center gap-10 px-5 py-14 lg:grid-cols-2 lg:gap-12 lg:py-20">
          <div class="flex min-w-0 flex-col gap-6">
            <p class="text-primary-200 inline-flex items-center gap-2 text-xs font-semibold tracking-widest uppercase">
              <span
                aria-hidden="true"
                class="bg-primary-300 size-2 rounded-full"
              />

              {{ $t('site.landing.kicker') }}
            </p>

            <h1 class="text-3xl leading-tight font-bold tracking-tight text-balance sm:text-5xl">
              <span class="block">{{ $t('site.landing.title') }}</span>

              <span class="text-primary-300 block">{{ $t('site.landing.ask') }}</span>
            </h1>

            <p class="text-primary-100 text-base leading-relaxed sm:text-lg">
              {{ $t('site.landing.lede') }}
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
              <button
                class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick inline-flex items-center justify-center gap-2 rounded-md px-6 py-3.5 text-base font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2"
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
                class="touch-target border-primary-300/40 hover:bg-primary-700 motion-quick inline-flex items-center justify-center rounded-md border px-6 py-3.5 text-base font-semibold transition"
                exact-active-class=""
                :to="{ name: SITE_ROUTE_NAMES.home, hash: `#${SECTOR_GRID_ANCHOR}` }"
              >
                {{ $t('site.landing.sectorsLink') }}
              </RouterLink>
            </div>

            <MCbsCountdown class="max-w-md" />
          </div>

          <MDiagnosticPreview />
        </div>
      </section>

      <section class="mx-auto -mt-8 w-full max-w-6xl px-5">
        <ul
          class="bg-panel shadow-lift-lg relative grid grid-cols-2 gap-px overflow-hidden rounded-xl lg:grid-cols-4"
          data-testid="proof"
          :aria-label="$t('site.landing.proofLabel')"
        >
          <li
            v-for="point in PROOF_POINTS"
            class="bg-panel flex flex-col gap-1 p-5 sm:p-6"
            :key="point.key"
          >
            <strong class="text-heading text-2xl font-bold sm:text-3xl">
              {{ point.figure }}
            </strong>

            <span class="text-muted text-sm">
              {{ $t(`site.landing.proof.${point.key}`) }}
            </span>
          </li>
        </ul>
      </section>

      <section class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-5 py-16">
        <header class="flex flex-col gap-2">
          <p class="text-muted text-xs font-semibold tracking-widest uppercase">
            {{ $t('site.landing.changesKicker') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight text-balance sm:text-3xl">
            {{ $t('site.landing.changesTitle') }}
          </h2>
        </header>

        <ol
          class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
          data-testid="what-changes"
        >
          <li
            v-for="(change, index) in WHAT_CHANGES"
            class="bg-panel shadow-lift flex flex-col gap-2 rounded-lg p-5"
            :key="change"
          >
            <span class="text-primary-400 text-sm font-bold tabular-nums">
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
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-5 py-16">
          <header class="flex flex-col gap-2">
            <p class="text-muted text-xs font-semibold tracking-widest uppercase">
              {{ $t('site.landing.timelineKicker') }}
            </p>

            <h2 class="text-heading text-2xl font-bold tracking-tight text-balance sm:text-3xl">
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
            class="bg-panel text-body shadow-lift flex flex-col gap-2 rounded-lg p-4 text-sm sm:flex-row sm:items-center sm:gap-3"
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
        class="mx-auto flex w-full max-w-6xl scroll-mt-20 flex-col gap-8 px-5 py-16"
      >
        <header class="flex flex-col gap-2">
          <p class="text-muted text-xs font-semibold tracking-widest uppercase">
            {{ $t('site.landing.sectorsKicker') }}
          </p>

          <h2 class="text-heading text-2xl font-bold tracking-tight text-balance sm:text-3xl">
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

      <section class="mx-auto w-full max-w-6xl px-5 pb-16">
        <div
          class="from-primary-500 to-primary-800 text-primary-contrast flex flex-col items-start gap-6 rounded-xl bg-linear-to-r p-8 sm:p-10 lg:flex-row lg:items-center lg:justify-between"
        >
          <div class="flex flex-col gap-2">
            <h2 class="text-2xl font-bold tracking-tight text-balance sm:text-3xl">
              {{ $t('site.landing.closingTitle') }}
            </h2>

            <p class="text-primary-100">
              {{ $t('site.landing.closingText') }}
            </p>
          </div>

          <button
            class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick shrink-0 rounded-md px-6 py-3.5 text-base font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2"
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
