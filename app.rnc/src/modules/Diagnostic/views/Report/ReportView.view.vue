<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import type { PublicReport } from '../../interfaces';
import { SITE_ROUTE_NAMES, usePublicFirm, useSeo } from '@SiteModule';
import { useMeetingTime, useProfileLine } from '../../composables';
import { GetReportService } from '../../services';

/*
 * The "Relatório de Impacto" (rnc-1 R17, plan AM4, AM15) at `/relatorio/:token` — the link of the
 * confirmation e-mail, open to anyone holding it for 30 days. The company, the locked result, the
 * 2026–2033 simulator and the meeting, printable: the site's chrome and this page's own buttons
 * hide on paper. A dead link is a calm message with the way back to the diagnostic.
 */
const { firm, load } = usePublicFirm();
const route = useRoute();
const { t } = useI18n();
const { formatMeeting } = useMeetingTime();

const isMissing = ref(false);
const report = ref<PublicReport | null>(null);

const { profileLine } = useProfileLine(() => report.value?.profile ?? null);

const firmLine = computed(() => [firm.value?.name, firm.value?.address, firm.value?.phone].filter(Boolean).join(' · '));

const seo = computed(() => ({
  description: t('diagnostic.report.seoDescription'),
  siteName: firm.value?.name ?? null,
  title: t('diagnostic.report.seoTitle'),
}));

useSeo(seo);

onMounted(onComponentMount);

// EVENTS
function onComponentMount() {
  const token = typeof route.params.token === 'string' ? route.params.token : '';

  load();

  GetReportService(token)
    .then(({ data }) => (report.value = data))
    .catch(() => (isMissing.value = true));
}

function onPrint() {
  window.print();
}
</script>

<template>
  <ThePublicLayout>
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-5 py-10 lg:py-14 print:max-w-none print:p-0">
      <div
        v-if="isMissing"
        class="bg-panel shadow-lift flex flex-col items-start gap-4 rounded-xl p-6 sm:p-8"
        data-testid="report-missing"
      >
        <h1 class="text-heading text-2xl font-bold tracking-tight">
          {{ $t('diagnostic.report.missingTitle') }}
        </h1>

        <p class="text-body leading-relaxed">
          {{ $t('diagnostic.report.missingText') }}
        </p>

        <RouterLink
          class="touch-target bg-primary-500 text-primary-contrast hover:bg-primary-600 inline-flex items-center gap-2 rounded-md px-5 py-3 font-semibold"
          exact-active-class=""
          :to="{ name: SITE_ROUTE_NAMES.diagnostic }"
        >
          {{ $t('diagnostic.report.missingAction') }}
        </RouterLink>
      </div>

      <div
        v-else-if="!report"
        class="flex flex-col gap-4"
        :aria-label="$t('diagnostic.report.loading')"
      >
        <Skeleton height="9rem" />

        <Skeleton height="14rem" />

        <Skeleton height="12rem" />
      </div>

      <template v-else>
        <div class="flex justify-end print:hidden">
          <Button
            data-testid="report-print"
            icon="pi pi-print"
            severity="secondary"
            :label="$t('diagnostic.report.print')"
            @click="onPrint"
          />
        </div>

        <article
          class="bg-panel shadow-lift overflow-hidden rounded-xl print:rounded-none print:shadow-none"
          data-testid="impact-report"
        >
          <header class="bg-primary-950 flex flex-col gap-4 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8">
            <div class="flex min-w-0 flex-col gap-2">
              <span class="text-primary-300 text-xs font-semibold tracking-widest uppercase">
                {{ firm?.name ?? $t('diagnostic.report.kicker') }}
              </span>

              <h1 class="text-primary-50 text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                {{ $t('diagnostic.report.title') }}
              </h1>
            </div>

            <div class="flex min-w-0 flex-col gap-1 sm:items-end sm:text-right">
              <span class="text-primary-200 text-xs font-semibold tracking-widest uppercase">
                {{ $t('diagnostic.report.issuedFor') }}
              </span>

              <strong
                class="text-primary-50 text-lg break-words"
                data-testid="report-company"
              >
                {{ report.company }}
              </strong>
            </div>
          </header>

          <div class="flex flex-col gap-8 p-6 sm:p-8">
            <MDiagnosticResult
              :profile-line
              :result="report.result"
            />

            <div class="break-inside-avoid">
              <MImpactSimulator
                :regime="report.profile.regime"
                :simulation="report.simulation"
              />
            </div>

            <div
              v-if="report.meeting"
              class="border-primary-200 bg-primary-50 flex break-inside-avoid items-start gap-4 rounded-lg border p-5"
              data-testid="report-meeting"
            >
              <i
                aria-hidden="true"
                class="pi pi-calendar text-primary-700 mt-0.5 text-xl"
              />

              <div class="flex min-w-0 flex-col gap-1">
                <strong class="text-heading">{{ $t('diagnostic.report.nextStep') }}</strong>

                <span class="text-body">
                  {{
                    $t('diagnostic.report.meeting', {
                      meeting: formatMeeting(report.meeting.starts_at),
                      specialist: report.meeting.specialist,
                    })
                  }}
                </span>
              </div>
            </div>

            <p class="text-muted text-xs leading-relaxed">
              {{ $t('diagnostic.report.legal') }}
            </p>
          </div>

          <footer
            v-if="firmLine"
            class="border-line text-muted border-t px-6 py-4 text-xs sm:px-8"
          >
            {{ firmLine }}
          </footer>
        </article>
      </template>
    </section>
  </ThePublicLayout>
</template>
