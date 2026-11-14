<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import type { DeepReadonly } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type { PublicFirmHours } from '@SiteModule';
import { useFirmBrand } from '@Composables';
import {
  brandOfTab,
  isBrandPreview,
  SECTOR_GRID_ANCHOR,
  SITE_ROUTE_NAMES,
  TRACKING_EVENT_TYPE,
  usePublicFirm,
  useSubBrandTheme,
  useTracking,
} from '@SiteModule';

/*
 * The public site's shell (rnc-1 R3, R5): Franco's header with the CTA, and the footer with the
 * public contact, the hours and the credentials — all read from `GET /api/public/firm`, so a second
 * firm (Phase 3) is a row, not a build.
 *
 * Under a sub-brand (rnc-3 R2, R3) the same shell is the brand's: its colours over the whole document
 * (`useBrandTheme`), its name in the mark and the copyright, its tagline in the footer — over Franco's
 * own address, phone, mailbox and hours. While the brand's answer is on its way every point that
 * writes the brand — both marks, the footer's tagline and the copyright — waits unseen on
 * `usePublicFirm`'s one `isBrandPending` (review S1), so Franco's name never flashes on an Express page. The operator's "Ver como" tab (`?previa=1`,
 * R5) wears a banner saying so.
 *
 * rnc-4 R10: the firm's own palette is `App.vue`'s (`useBrandTheme`, both layouts); this shell only
 * lays a sub-brand's over it while it is up. The mark writes the firm's logo text — the tab's last
 * answer (`useFirmBrand`) until this page's own arrives — and stays unseen while neither is known.
 */
const { logoText: hostLogoText } = useFirmBrand();
const { firm, isBrandPending, load } = usePublicFirm();
const { push } = useRouter();
const { t } = useI18n();
const { track } = useTracking();

useSubBrandTheme(firm);

const CURRENT_YEAR = new Date().getFullYear();
const NAV_LINKS = [
  { key: 'home', to: { name: SITE_ROUTE_NAMES.home } },
  { key: 'sectors', to: { hash: `#${SECTOR_GRID_ANCHOR}`, name: SITE_ROUTE_NAMES.home } },
] as const;

const isPreview = ref(false);

const footerTagline = computed(() => firm.value?.tagline ?? t('site.footer.tagline'));
const hoursLines = computed(() => (firm.value?.business_hours ?? []).map(formatHours));
const logoName = computed(() => firm.value?.logo_text || hostLogoText.value);
const isShellPending = computed(() => isBrandPending.value || !logoName.value);

watch(firm, onFirmChange);
// A refused brand was dropped on the way (AM21): the shell asks for the main brand's firm.
watch(brandOfTab, onBrandChange);

onMounted(onComponentMount);

// HELPERS
function formatDays(weekdays: readonly number[]): string {
  const names = weekdays.map((weekday) => t(`site.weekdays.d${weekday}`));
  const first = weekdays[0] ?? 0;
  const isRange = weekdays.length > 2 && weekdays.every((weekday, index) => weekday === first + index);

  // The last day of a range sits mid-sentence: "Segunda a quinta", never "Segunda a Quinta".
  const last = t(`site.weekdaysInSentence.d${weekdays[weekdays.length - 1]}`);

  return isRange ? t('site.footer.dayRange', { first: names[0], last }) : names.join(', ');
}

function formatHours(hours: DeepReadonly<PublicFirmHours>): string {
  return t('site.footer.hoursRange', {
    days: formatDays(hours.weekdays),
    from: formatTime(hours.from),
    to: formatTime(hours.to),
  });
}

function formatTime(time: string): string {
  const [hour = '0', minute = '00'] = time.split(':');

  return minute === '00' ? `${Number(hour)}h` : `${Number(hour)}h${minute}`;
}

// EVENTS
function onBrandChange() {
  load();
}

function onComponentMount() {
  onFirmChange();
  load();
}

function onFirmChange() {
  if (!firm.value) return;

  isPreview.value = Boolean(firm.value.brand) && isBrandPreview();
}

function onDiagnosticClick() {
  track(TRACKING_EVENT_TYPE.CTA);

  push({ name: SITE_ROUTE_NAMES.diagnostic });
}
</script>

<template>
  <div class="bg-canvas text-body flex min-h-dvh w-full flex-col overflow-x-clip">
    <!-- `print:hidden` on the chrome: the printable pages (the "Relatório de Impacto", F4) print only their own header. -->
    <div
      v-if="isPreview"
      class="bg-primary-950 text-primary-50 print:hidden"
      data-testid="brand-preview-banner"
      role="status"
    >
      <p class="mx-auto flex w-full max-w-6xl items-center gap-2 px-5 py-2 text-xs font-medium sm:text-sm">
        <i
          aria-hidden="true"
          class="pi pi-eye text-primary-300"
        />

        {{ $t('site.preview.banner', { name: firm?.name ?? '' }) }}
      </p>
    </div>

    <header class="border-line bg-canvas/90 sticky top-0 z-20 border-b backdrop-blur print:hidden">
      <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-5 py-3">
        <!-- `exact-active-class` empty: the theme's glowing bar marks the back office's rail, not the site.
             Each logo sits in its own span, because ALogo's own `inline-flex` outvotes a `hidden`. -->
        <RouterLink
          exact-active-class=""
          :class="[
            'touch-target motion-quick shrink-0 text-lg transition-opacity sm:text-xl',
            { 'opacity-0': isShellPending },
          ]"
          :to="{ name: SITE_ROUTE_NAMES.home }"
        >
          <span class="sm:hidden">
            <ALogo
              variant="min"
              :name="logoName"
            />
          </span>

          <span class="hidden sm:inline">
            <ALogo :name="logoName" />
          </span>
        </RouterLink>

        <nav
          class="hidden items-center gap-6 text-sm font-semibold md:flex"
          :aria-label="$t('site.nav.label')"
        >
          <RouterLink
            v-for="link in NAV_LINKS"
            class="touch-target text-muted hover:text-heading"
            exact-active-class="text-heading"
            :key="link.key"
            :to="link.to"
          >
            {{ $t(`site.nav.${link.key}`) }}
          </RouterLink>
        </nav>

        <div class="flex min-w-0 items-center gap-3">
          <a
            v-if="firm?.phone_href"
            class="touch-target text-muted hover:text-heading hidden text-sm font-semibold lg:inline"
            :href="firm.phone_href"
          >
            {{ firm.phone }}
          </a>

          <button
            class="touch-target bg-primary-500 text-primary-contrast hover:bg-primary-600 focus-visible:outline-primary-500 motion-quick rounded-md px-4 py-2.5 text-sm font-semibold whitespace-nowrap transition focus-visible:outline-2 focus-visible:outline-offset-2"
            data-testid="header-cta"
            type="button"
            @click="onDiagnosticClick"
          >
            {{ $t('site.nav.cta') }}
          </button>
        </div>
      </div>

      <nav
        class="border-line flex justify-center gap-8 border-t px-5 py-2 text-sm font-semibold md:hidden"
        :aria-label="$t('site.nav.label')"
      >
        <RouterLink
          v-for="link in NAV_LINKS"
          class="touch-target text-muted hover:text-heading"
          exact-active-class="text-heading"
          :key="link.key"
          :to="link.to"
        >
          {{ $t(`site.nav.${link.key}`) }}
        </RouterLink>
      </nav>
    </header>

    <main class="flex w-full flex-1 flex-col">
      <slot />
    </main>

    <footer
      class="bg-panel border-line border-t print:hidden"
      data-testid="site-footer"
    >
      <div class="mx-auto grid w-full max-w-6xl gap-8 px-5 py-12 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div
          data-testid="footer-brand"
          :class="['motion-quick flex flex-col gap-3 transition-opacity', { 'opacity-0': isShellPending }]"
        >
          <ALogo
            class="text-lg"
            :name="logoName"
          />

          <p class="text-muted leading-relaxed">
            {{ footerTagline }}
          </p>
        </div>

        <address class="text-muted flex flex-col gap-2 not-italic">
          <span class="text-heading font-semibold">
            {{ $t('site.footer.contact') }}
          </span>

          <template v-if="firm">
            <span v-if="firm.address">{{ firm.address }}</span>

            <a
              v-if="firm.phone_href"
              class="touch-target hover:text-heading self-start underline-offset-4 hover:underline"
              :href="firm.phone_href"
            >
              {{ firm.phone }}
            </a>

            <a
              v-if="firm.mailbox"
              class="touch-target hover:text-heading self-start break-all underline-offset-4 hover:underline"
              :href="`mailto:${firm.mailbox}`"
            >
              {{ firm.mailbox }}
            </a>
          </template>
        </address>

        <div class="text-muted flex flex-col gap-2">
          <span class="text-heading font-semibold">
            {{ $t('site.footer.hours') }}
          </span>

          <span
            v-for="line in hoursLines"
            :key="line"
          >
            {{ line }}
          </span>
        </div>

        <div class="text-muted flex flex-col gap-2">
          <span class="text-heading font-semibold">
            {{ $t('site.footer.credentials') }}
          </span>

          <span class="leading-relaxed">
            {{ $t('site.footer.credentialsText') }}
          </span>
        </div>
      </div>

      <div class="border-line border-t">
        <div
          class="text-muted mx-auto flex w-full max-w-6xl flex-col gap-2 px-5 py-5 text-xs sm:flex-row sm:justify-between"
        >
          <span>{{ $t('site.footer.disclaimer') }}</span>

          <span
            data-testid="footer-copyright"
            :class="['motion-quick transition-opacity', { 'opacity-0': isShellPending }]"
          >
            {{ $t('site.footer.copyright', { year: CURRENT_YEAR, name: firm?.name ?? '' }) }}
          </span>
        </div>
      </div>
    </footer>
  </div>
</template>
