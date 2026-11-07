<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { SITE_ROUTE_NAMES, TRACKING_EVENT_TYPE, usePublicFirm, useSeo, useTracking } from '@SiteModule';
import { useDiagnostic, useMeetingTime, useProfileLine } from '../../composables';
import { B2B_OPTIONS, BAND_OPTIONS, DIAGNOSTIC_ROUTE_NAMES, REGIME_OPTIONS, SECTOR_OPTIONS } from '../../constants';
import { DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS } from '../../enums';

/*
 * The public diagnostic (rnc-1 F3, R9–R14) at `/diagnostico`, where every CTA of the site lands.
 * Step 1 by the CNPJ or the quiz, the contact right after it (the lead exists from then on, R11),
 * the profile, the faturamento, then the locked result with the 2026–2033 simulator, the
 * "Agendar com um especialista" slot picker (F4) and the confirmation with the report's link.
 */
const { firm, load } = usePublicFirm();
const route = useRoute();
const { t } = useI18n();
const { track } = useTracking();
const {
  booking,
  choosePath,
  company,
  confirmBooking,
  confirmCompany,
  consentText,
  errorKey,
  finish,
  hasEmail,
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
  token,
} = useDiagnostic();
const { formatMeeting } = useMeetingTime();
const { profileLine } = useProfileLine(profile);

const MINUTE_MS = 60_000;

const asksRegimeAfterLookup = computed(() => lookupStatus.value === LOOKUP_STATUS.FOUND && !company.value?.regime);
const isCompanyFound = computed(() => lookupStatus.value === LOOKUP_STATUS.FOUND);

const meetingMinutes = computed(() => {
  const meeting = booking.value?.meeting;

  return meeting ? Math.round((Date.parse(meeting.ends_at) - Date.parse(meeting.starts_at)) / MINUTE_MS) : 0;
});

const seo = computed(() => ({
  description: t('site.seo.diagnosticDescription'),
  siteName: firm.value?.name ?? null,
  title: t('site.seo.diagnosticTitle'),
}));

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
        <!-- One step leaves before the next arrives (`out-in`): keyed on the step AND the path, so
             picking "CNPJ" or "responder" crosses over too. The styles are in motion.css. -->
        <Transition
          mode="out-in"
          name="step-fade"
        >
          <div
            class="flex flex-col gap-6"
            :key="`${step}-${path ?? ''}`"
          >
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

            <MSlotPicker
              v-else-if="step === DIAGNOSTIC_STEP.BOOKING && token"
              :needs-email="!hasEmail"
              :token
              @on-booked="confirmBooking"
            />

            <div
              v-else-if="step === DIAGNOSTIC_STEP.BOOKED && booking"
              class="flex flex-col gap-6"
              data-testid="booking-confirmed"
            >
              <div class="border-success-200 bg-success-50 flex items-start gap-4 rounded-lg border p-5">
                <span
                  aria-hidden="true"
                  class="bg-success-600 text-success-50 flex size-10 shrink-0 items-center justify-center rounded-full"
                >
                  <i class="pi pi-check" />
                </span>

                <div class="flex min-w-0 flex-col gap-1">
                  <strong
                    class="text-heading text-xl first-letter:uppercase"
                    data-testid="booking-when"
                  >
                    {{ formatMeeting(booking.meeting.starts_at) }}
                  </strong>

                  <span class="text-body">
                    {{ $t('diagnostic.booking.confirmed.with', { specialist: booking.meeting.specialist }) }}
                    <template v-if="booking.meeting.area">· {{ booking.meeting.area }}</template>
                  </span>

                  <span class="text-muted text-sm">
                    {{ $t('diagnostic.booking.confirmed.length', { minutes: meetingMinutes }) }}
                  </span>
                </div>
              </div>

              <p
                v-if="result"
                class="text-body"
              >
                {{
                  $t('diagnostic.booking.confirmed.found', {
                    company: result.company ?? company?.name ?? '',
                    opportunities: $t('diagnostic.result.opportunities', result.opportunities),
                    risks: $t('diagnostic.result.risks', result.risks),
                  })
                }}
              </p>

              <p class="text-muted text-sm leading-relaxed">
                {{ $t('diagnostic.booking.confirmed.sent') }}
              </p>

              <div class="flex flex-col gap-3 sm:flex-row">
                <RouterLink
                  class="touch-target bg-primary-500 text-primary-contrast hover:bg-primary-600 inline-flex items-center justify-center gap-2 rounded-md px-5 py-3 font-semibold"
                  data-testid="open-report"
                  exact-active-class=""
                  :to="{ name: DIAGNOSTIC_ROUTE_NAMES.report, params: { token: booking.report_token } }"
                >
                  <i
                    aria-hidden="true"
                    class="pi pi-file"
                  />

                  {{ $t('diagnostic.booking.confirmed.openReport') }}
                </RouterLink>
              </div>
            </div>
          </div>
        </Transition>

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
