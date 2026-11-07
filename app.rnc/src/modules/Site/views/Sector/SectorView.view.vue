<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { SectorPage, SectorPageSummary } from '../../interfaces';
import { HTTP_STATUS } from '@Enums';
import { usePublicFirm, useSeo, useTracking } from '../../composables';
import { SECTOR_GRID_ANCHOR, SITE_ROUTE_NAMES } from '../../constants';
import { TRACKING_EVENT_TYPE } from '../../enums';
import { GetSectorPageService, GetSectorPagesService } from '../../services';

const { firm, load } = usePublicFirm();
const route = useRoute();
const { push } = useRouter();
const { t } = useI18n();
const { track } = useTracking();

const hasFailed = ref(false);
const isLoading = ref(true);
const isMissing = ref(false);
const page = ref<SectorPage | null>(null);
const sectors = ref<SectorPageSummary[]>([]);

const slug = computed(() => String(route.params.slug ?? ''));
const otherSectors = computed(() => sectors.value.filter((sector) => sector.slug !== slug.value));

const seo = computed(() => {
  if (isMissing.value)
    return {
      description: t('site.sector.notFoundText'),
      siteName: firm.value?.name ?? null,
      title: t('site.sector.notFoundTitle'),
    };

  if (!page.value) return null;

  return { description: page.value.seo.description, siteName: firm.value?.name ?? null, title: page.value.seo.title };
});

useSeo(seo);

watch(slug, onSlugChange);

onMounted(onComponentMount);

// EVENTS
function fetchPage() {
  isLoading.value = true;
  isMissing.value = false;
  hasFailed.value = false;

  GetSectorPageService(slug.value)
    .then(onPageLoaded)
    .catch(onPageFailed)
    .finally(() => (isLoading.value = false));
}

function fetchSectors() {
  GetSectorPagesService()
    .then(({ data }) => (sectors.value = data))
    .catch(() => (sectors.value = []));
}

function onComponentMount() {
  load();

  fetchPage();

  fetchSectors();
}

function onDiagnosticClick() {
  track(TRACKING_EVENT_TYPE.CTA, slug.value);

  push({ name: SITE_ROUTE_NAMES.diagnostic, query: { setor: slug.value } });
}

function onPageFailed(error: { response?: { status?: number } }) {
  page.value = null;

  if (error.response?.status === HTTP_STATUS.NOT_FOUND) {
    isMissing.value = true;
    return;
  }

  hasFailed.value = true;
}

function onPageLoaded({ data }: { data: SectorPage }) {
  page.value = data;

  track(TRACKING_EVENT_TYPE.VISIT, data.slug);
}

function onSlugChange() {
  fetchPage();
}
</script>

<template>
  <ThePublicLayout>
    <article
      class="flex flex-col"
      data-testid="sector-page"
      :data-sector="slug"
    >
      <header class="from-primary-500 to-primary-800 text-primary-contrast bg-linear-to-br">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-5 py-12 lg:py-16">
          <RouterLink
            class="touch-target text-primary-200 hover:text-primary-contrast inline-flex items-center gap-2 self-start text-sm font-semibold"
            :to="{ name: SITE_ROUTE_NAMES.home, hash: `#${SECTOR_GRID_ANCHOR}` }"
          >
            <i
              aria-hidden="true"
              class="pi pi-arrow-left text-xs"
            />

            {{ $t('site.sector.back') }}
          </RouterLink>

          <template v-if="page">
            <p class="text-primary-200 text-xs font-semibold tracking-widest uppercase">
              {{ $t('site.sector.kicker') }} · {{ page.label }}
            </p>

            <h1 class="text-3xl leading-tight font-bold tracking-tight text-balance sm:text-5xl">
              {{ page.title }}
            </h1>

            <p class="text-primary-100 max-w-3xl text-base leading-relaxed sm:text-lg">
              {{ page.lede }}
            </p>
          </template>

          <template v-else-if="isLoading">
            <Skeleton
              height="1rem"
              width="10rem"
            />

            <Skeleton
              height="3rem"
              width="70%"
            />
          </template>

          <h1
            v-else-if="isMissing"
            class="text-3xl font-bold tracking-tight sm:text-4xl"
          >
            {{ $t('site.sector.notFoundTitle') }}
          </h1>

          <h1
            v-else
            class="text-3xl font-bold tracking-tight sm:text-4xl"
          >
            {{ $t('site.sector.failedTitle') }}
          </h1>
        </div>
      </header>

      <div class="mx-auto flex w-full max-w-6xl flex-col gap-10 px-5 py-12">
        <section
          v-if="page"
          class="flex flex-col gap-5"
        >
          <h2 class="text-heading text-xl font-bold sm:text-2xl">
            {{ $t('site.sector.factsTitle') }}
          </h2>

          <ol class="flex flex-col gap-3">
            <li
              v-for="(fact, index) in page.facts"
              class="bg-panel shadow-lift grid gap-3 rounded-lg p-5 sm:grid-cols-[9rem_1fr] sm:gap-6"
              data-testid="sector-fact"
              :key="index"
            >
              <span class="text-heading text-lg font-bold tabular-nums">
                {{ fact.date }}
              </span>

              <div class="flex min-w-0 flex-col gap-2">
                <p class="text-body leading-relaxed">
                  {{ fact.text }}
                </p>

                <p class="text-muted text-xs">
                  {{ $t('site.sector.source') }}:

                  <a
                    v-if="fact.source.url"
                    class="hover:text-heading break-words underline underline-offset-4"
                    rel="noopener"
                    target="_blank"
                    :href="fact.source.url"
                  >
                    {{ fact.source.label }}
                  </a>

                  <span v-else>{{ fact.source.label }}</span>
                </p>
              </div>
            </li>
          </ol>
        </section>

        <div
          v-else-if="isLoading"
          class="flex flex-col gap-3"
        >
          <Skeleton
            v-for="placeholder in 4"
            class="rounded-lg"
            height="5rem"
            :key="placeholder"
          />
        </div>

        <div
          v-else
          class="bg-panel shadow-lift flex flex-col items-start gap-3 rounded-lg p-6"
          :data-testid="isMissing ? 'sector-missing' : 'sector-failed'"
        >
          <p class="text-body">
            {{ isMissing ? $t('site.sector.notFoundText') : $t('site.sector.failedText') }}
          </p>

          <Button
            v-if="hasFailed"
            icon="pi pi-refresh"
            severity="secondary"
            :label="$t('site.sector.retry')"
            @click="fetchPage"
          />
        </div>

        <section
          v-if="page"
          class="from-primary-500 to-primary-800 text-primary-contrast flex flex-col items-start gap-5 rounded-xl bg-linear-to-r p-8 lg:flex-row lg:items-center lg:justify-between"
        >
          <div class="flex flex-col gap-2">
            <h2 class="text-xl font-bold text-balance sm:text-2xl">
              {{ $t('site.sector.ctaTitle', { sector: page.label.toLowerCase() }) }}
            </h2>

            <p class="text-primary-100">
              {{ $t('site.sector.ctaText') }}
            </p>
          </div>

          <button
            class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 focus-visible:outline-primary-300 motion-quick shrink-0 rounded-md px-6 py-3.5 text-base font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2"
            data-testid="sector-cta"
            type="button"
            @click="onDiagnosticClick"
          >
            {{ $t('site.cta.primary') }}
          </button>
        </section>

        <section
          v-if="otherSectors.length"
          class="flex flex-col gap-5"
        >
          <h2 class="text-heading text-xl font-bold">
            {{ $t('site.sector.others') }}
          </h2>

          <MSectorGrid :sectors="otherSectors" />
        </section>
      </div>
    </article>
  </ThePublicLayout>
</template>
