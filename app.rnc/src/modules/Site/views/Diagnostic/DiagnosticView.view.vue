<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePublicFirm, useSeo, useTracking } from '../../composables';
import { SITE_ROUTE_NAMES } from '../../constants';
import { TRACKING_EVENT_TYPE } from '../../enums';

/*
 * Where every CTA of the site lands (rnc-1 F2 W12). The diagnostic itself is F3, which replaces this
 * view; until then the page says so and hands the visitor Franco's public contact.
 */
const { firm, load } = usePublicFirm();
const { t } = useI18n();
const { track } = useTracking();

const seo = computed(() => ({
  description: t('site.seo.diagnosticDescription'),
  siteName: firm.value?.name ?? null,
  title: t('site.seo.diagnosticTitle'),
}));

useSeo(seo);

onMounted(onComponentMount);

// EVENTS
function onComponentMount() {
  load();

  track(TRACKING_EVENT_TYPE.VISIT);
}
</script>

<template>
  <ThePublicLayout>
    <section
      class="mx-auto flex w-full max-w-3xl flex-col items-start gap-6 px-5 py-16 lg:py-24"
      data-testid="diagnostic-placeholder"
    >
      <p class="text-muted text-xs font-semibold tracking-widest uppercase">
        {{ $t('site.diagnostic.kicker') }}
      </p>

      <h1 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
        {{ $t('site.diagnostic.title') }}
      </h1>

      <p class="text-body text-lg leading-relaxed">
        {{ $t('site.diagnostic.text') }}
      </p>

      <div
        v-if="firm"
        class="flex flex-col gap-3 sm:flex-row"
      >
        <a
          v-if="firm.phone_href"
          class="touch-target bg-primary-500 text-primary-contrast hover:bg-primary-600 inline-flex items-center gap-2 rounded-md px-5 py-3 font-semibold"
          :href="firm.phone_href"
        >
          <i
            aria-hidden="true"
            class="pi pi-phone"
          />

          {{ firm.phone }}
        </a>

        <a
          v-if="firm.mailbox"
          class="touch-target border-line-strong text-heading hover:bg-panel-muted inline-flex items-center gap-2 rounded-md border px-5 py-3 font-semibold"
          :href="`mailto:${firm.mailbox}`"
        >
          <i
            aria-hidden="true"
            class="pi pi-envelope"
          />

          {{ firm.mailbox }}
        </a>
      </div>

      <RouterLink
        class="touch-target text-muted hover:text-heading inline-flex items-center gap-2 text-sm font-semibold"
        :to="{ name: SITE_ROUTE_NAMES.home }"
      >
        <i
          aria-hidden="true"
          class="pi pi-arrow-left text-xs"
        />

        {{ $t('site.diagnostic.back') }}
      </RouterLink>
    </section>
  </ThePublicLayout>
</template>
