<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { SITE_ROUTE_NAMES, TRACKING_EVENT_TYPE, usePublicFirm, useSeo, useTracking } from '@SiteModule';
import { useDiagnostic } from '../../composables';
import { B2B_OPTIONS, BAND_OPTIONS, REGIME_OPTIONS, SECTOR_OPTIONS } from '../../constants';
import { DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS } from '../../enums';

/*
 * The public diagnostic (rnc-1 F3, R9–R14) at `/diagnostico`, where every CTA of the site lands.
 * Step 1 by the CNPJ or the quiz, the contact right after it (the lead exists from then on, R11),
 * the profile, the faturamento, then the locked result with the 2026–2033 simulator and the
 * "Agendar com um especialista" CTA that F4 wires to the slot picker (W20).
 */
const { firm, load } = usePublicFirm();
const route = useRoute();
const { t } = useI18n();
const { track } = useTracking();
const {
  choosePath,
  company,
  confirmCompany,
  consentText,
  errorKey,
  finish,
  isSaving,
  lookupCnpj,
  lookupStatus,
  lookupStepIndex,
  openBooking,
  path,
  presetSector,
  profile,
  restart,
  result,
  saveContact,
  saveProfile,
  simulation,
  step,
} = useDiagnostic();

const asksRegimeAfterLookup = computed(() => lookupStatus.value === LOOKUP_STATUS.FOUND && !company.value?.regime);
const isCompanyFound = computed(() => lookupStatus.value === LOOKUP_STATUS.FOUND);

const seo = computed(() => ({
  description: t('site.seo.diagnosticDescription'),
  siteName: firm.value?.name ?? null,
  title: t('site.seo.diagnosticTitle'),
}));

const profileLine = computed(() => {
  const { b2b, band, regime, sector } = profile.value;

  return [
    sector && t(`diagnostic.sectors.${sector}`),
    regime && t(`diagnostic.regimes.${regime}`),
    band && t(`diagnostic.bands.${band}`),
    b2b !== null && t('diagnostic.result.b2bShare', { value: b2b }),
  ]
    .filter(Boolean)
    .join(' · ');
});

useSeo(seo);

onMounted(onComponentMount);

// EVENTS
function onComponentMount() {
  const preset = typeof route.query.setor === 'string' ? route.query.setor : null;
  const isKnownSector = SECTOR_OPTIONS.some((option) => option.value === preset);

  load();

  presetSector(isKnownSector ? preset : null);

  track(TRACKING_EVENT_TYPE.VISIT, isKnownSector ? preset : null);
}

function onSwitchPath() {
  choosePath(path.value === DIAGNOSTIC_PATH.CNPJ ? DIAGNOSTIC_PATH.QUIZ : DIAGNOSTIC_PATH.CNPJ);
}
</script>

<template>
  <ThePublicLayout>
    <section
      class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-5 py-10 lg:py-16"
      data-testid="diagnostic"
      :data-step="step"
    >
      <header class="flex flex-col gap-3">
        <p class="text-muted text-xs font-semibold tracking-widest uppercase">
          {{ $t('diagnostic.kicker') }}
        </p>

        <h1 class="text-heading text-3xl font-bold tracking-tight text-balance sm:text-4xl">
          {{ $t(`diagnostic.titles.${step}`) }}
        </h1>

        <p
          v-if="step === DIAGNOSTIC_STEP.COMPANY"
          class="text-body text-lg leading-relaxed"
        >
          {{ $t('diagnostic.lede') }}
        </p>
      </header>

      <MDiagnosticProgress :step />

      <div class="bg-panel shadow-lift flex flex-col gap-6 rounded-xl p-5 sm:p-8">
        <template v-if="step === DIAGNOSTIC_STEP.COMPANY">
          <div
            v-if="!path"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
          >
            <button
              class="touch-target border-line-strong hover:bg-panel-muted flex flex-col items-start gap-2 rounded-lg border p-5 text-left transition-colors"
              data-testid="path-cnpj"
              type="button"
              @click="choosePath(DIAGNOSTIC_PATH.CNPJ)"
            >
              <i
                aria-hidden="true"
                class="pi pi-building text-primary-600 text-xl"
              />

              <strong class="text-heading text-lg">
                {{ $t('diagnostic.path.cnpjTitle') }}
              </strong>

              <span class="text-muted text-sm">
                {{ $t('diagnostic.path.cnpjText') }}
              </span>
            </button>

            <button
              class="touch-target border-line-strong hover:bg-panel-muted flex flex-col items-start gap-2 rounded-lg border p-5 text-left transition-colors"
              data-testid="path-quiz"
              type="button"
              @click="choosePath(DIAGNOSTIC_PATH.QUIZ)"
            >
              <i
                aria-hidden="true"
                class="pi pi-list-check text-primary-600 text-xl"
              />

              <strong class="text-heading text-lg">
                {{ $t('diagnostic.path.quizTitle') }}
              </strong>

              <span class="text-muted text-sm">
                {{ $t('diagnostic.path.quizText') }}
              </span>
            </button>
          </div>

          <template v-else>
            <MCnpjLookup
              v-if="path === DIAGNOSTIC_PATH.CNPJ"
              :company
              :is-busy="isSaving"
              :status="lookupStatus"
              :step-index="lookupStepIndex"
              @on-lookup="lookupCnpj"
              @on-quiz="choosePath(DIAGNOSTIC_PATH.QUIZ)"
            />

            <template v-else>
              <MChoiceGroup
                v-model="profile.sector"
                label="diagnostic.fields.sector"
                name="sector"
                :options="SECTOR_OPTIONS"
              />

              <MChoiceGroup
                v-model="profile.regime"
                columns-class="grid-cols-2 sm:grid-cols-4"
                label="diagnostic.fields.regime"
                name="regime"
                :options="REGIME_OPTIONS"
              />
            </template>

            <MChoiceGroup
              v-if="asksRegimeAfterLookup"
              v-model="profile.regime"
              columns-class="grid-cols-2 sm:grid-cols-4"
              label="diagnostic.fields.regime"
              name="regime"
              :options="REGIME_OPTIONS"
            />

            <p
              v-if="errorKey"
              class="text-danger-700 text-sm"
              data-testid="step-error"
              role="alert"
            >
              {{ $t(errorKey) }}
            </p>

            <div class="flex flex-wrap items-center gap-4">
              <Button
                v-if="path === DIAGNOSTIC_PATH.QUIZ || isCompanyFound"
                data-testid="step-next"
                icon="pi pi-arrow-right"
                icon-pos="right"
                :label="
                  isCompanyFound && path === DIAGNOSTIC_PATH.CNPJ
                    ? $t('diagnostic.actions.confirmCompany')
                    : $t('diagnostic.actions.continue')
                "
                :loading="isSaving"
                @click="confirmCompany"
              />

              <button
                class="touch-target text-muted hover:text-heading text-sm font-semibold underline-offset-4 hover:underline"
                data-testid="path-switch"
                type="button"
                @click="onSwitchPath"
              >
                {{
                  path === DIAGNOSTIC_PATH.CNPJ
                    ? $t('diagnostic.path.switchToQuiz')
                    : $t('diagnostic.path.switchToCnpj')
                }}
              </button>
            </div>
          </template>
        </template>

        <MDiagnosticContactForm
          v-else-if="step === DIAGNOSTIC_STEP.CONTACT"
          :company="company?.name ?? null"
          :consent-text
          :error-key
          :is-saving
          @on-submit="saveContact"
        />

        <div
          v-else-if="step === DIAGNOSTIC_STEP.PROFILE"
          class="flex flex-col gap-6"
          data-testid="step-profile"
        >
          <MChoiceGroup
            v-model="profile.sector"
            label="diagnostic.fields.sector"
            name="sector"
            :options="SECTOR_OPTIONS"
          />

          <MChoiceGroup
            v-model="profile.regime"
            columns-class="grid-cols-2 sm:grid-cols-4"
            label="diagnostic.fields.regime"
            name="regime"
            :options="REGIME_OPTIONS"
          />

          <div class="flex flex-col gap-2">
            <MChoiceGroup
              v-model="profile.b2b"
              columns-class="grid-cols-4 sm:grid-cols-6"
              label="diagnostic.fields.b2b"
              name="b2b"
              :options="B2B_OPTIONS"
            />

            <small class="text-muted text-xs">
              {{ $t('diagnostic.fields.b2bHint') }}
            </small>
          </div>

          <p
            v-if="errorKey"
            class="text-danger-700 text-sm"
            data-testid="step-error"
            role="alert"
          >
            {{ $t(errorKey) }}
          </p>

          <div class="flex">
            <Button
              data-testid="step-next"
              icon="pi pi-arrow-right"
              icon-pos="right"
              :label="$t('diagnostic.actions.continue')"
              :loading="isSaving"
              @click="saveProfile"
            />
          </div>
        </div>

        <div
          v-else-if="step === DIAGNOSTIC_STEP.REVENUE"
          class="flex flex-col gap-6"
          data-testid="step-revenue"
        >
          <MChoiceGroup
            v-model="profile.band"
            columns-class="grid-cols-1 sm:grid-cols-2"
            label="diagnostic.fields.band"
            name="band"
            :options="BAND_OPTIONS"
          />

          <div class="flex flex-col gap-3">
            <label
              class="flex cursor-pointer items-center gap-3 text-sm"
              for="quiz-incentivos"
            >
              <Checkbox
                v-model="profile.incentivos"
                binary
                input-id="quiz-incentivos"
              />

              <span class="text-body">{{ $t('diagnostic.fields.incentivos') }}</span>
            </label>

            <label
              class="flex cursor-pointer items-center gap-3 text-sm"
              for="quiz-exporta"
            >
              <Checkbox
                v-model="profile.exporta"
                binary
                input-id="quiz-exporta"
              />

              <span class="text-body">{{ $t('diagnostic.fields.exporta') }}</span>
            </label>
          </div>

          <p
            v-if="errorKey"
            class="text-danger-700 text-sm"
            data-testid="step-error"
            role="alert"
          >
            {{ $t(errorKey) }}
          </p>

          <div class="flex">
            <Button
              data-testid="step-next"
              icon="pi pi-chart-bar"
              :label="$t('diagnostic.actions.seeResult')"
              :loading="isSaving"
              @click="finish"
            />
          </div>
        </div>

        <div
          v-else-if="step === DIAGNOSTIC_STEP.RESULT && result && simulation"
          class="flex flex-col gap-6"
        >
          <MDiagnosticResult
            :profile-line
            :result
          />

          <MImpactSimulator
            :regime="profile.regime"
            :simulation
          />

          <div class="bg-primary-950 flex flex-col gap-4 rounded-xl p-5 sm:flex-row sm:items-center sm:p-6">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
              <strong class="text-primary-50 text-lg">
                {{ $t('diagnostic.schedule.title') }}
              </strong>

              <span class="text-primary-100 text-sm">
                {{ $t('diagnostic.schedule.text') }}
              </span>
            </div>

            <button
              class="touch-target bg-primary-300 text-primary-950 hover:bg-primary-200 inline-flex shrink-0 items-center justify-center gap-2 rounded-md px-5 py-3 font-semibold transition-colors"
              data-testid="schedule-cta"
              type="button"
              @click="openBooking"
            >
              <i
                aria-hidden="true"
                class="pi pi-calendar"
              />

              {{ $t('diagnostic.schedule.action') }}
            </button>
          </div>
        </div>

        <div
          v-else-if="step === DIAGNOSTIC_STEP.BOOKING"
          class="flex flex-col items-start gap-4"
          data-testid="booking-soon"
        >
          <p class="text-body text-lg leading-relaxed">
            {{ $t('diagnostic.booking.text') }}
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
              class="touch-target border-line-strong text-heading hover:bg-panel-muted inline-flex items-center gap-2 rounded-md border px-5 py-3 font-semibold break-all"
              :href="`mailto:${firm.mailbox}`"
            >
              <i
                aria-hidden="true"
                class="pi pi-envelope"
              />

              {{ firm.mailbox }}
            </a>
          </div>
        </div>

        <p
          v-if="errorKey && step === DIAGNOSTIC_STEP.COMPANY && !path"
          class="text-danger-700 text-sm"
          data-testid="step-error"
          role="alert"
        >
          {{ $t(errorKey) }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-4">
        <button
          v-if="step !== DIAGNOSTIC_STEP.COMPANY || path"
          class="touch-target text-muted hover:text-heading inline-flex items-center gap-2 text-sm font-semibold"
          data-testid="diagnostic-restart"
          type="button"
          @click="restart"
        >
          <i
            aria-hidden="true"
            class="pi pi-refresh text-xs"
          />

          {{ $t('diagnostic.actions.restart') }}
        </button>

        <RouterLink
          class="touch-target text-muted hover:text-heading inline-flex items-center gap-2 text-sm font-semibold"
          exact-active-class=""
          :to="{ name: SITE_ROUTE_NAMES.home }"
        >
          <i
            aria-hidden="true"
            class="pi pi-arrow-left text-xs"
          />

          {{ $t('diagnostic.actions.back') }}
        </RouterLink>
      </div>
    </section>
  </ThePublicLayout>
</template>
