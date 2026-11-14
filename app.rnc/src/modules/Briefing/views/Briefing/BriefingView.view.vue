<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Briefing, BriefingAction, BriefingRange } from '../../interfaces';
import { vCountUp } from '@Directives';
import { getI18nRouteName } from '@Helpers';
import { useFiscalFormat } from '@FiscalModule';
import { PIPELINE_ROUTE_NAMES, useLeadFormat } from '@PipelineModule';
import { useUserStore } from '@UserModule';
import { BRIEFING_TIME_ZONE, GREETING_HOURS } from '../../constants';
import { BRIEFING_ACTION_KIND, BRIEFING_RADAR_PERIOD } from '../../enums';
import { GetBriefingService } from '../../services';

/*
 * "Briefing do dia" (rnc-3 R26; the demo's `briefing()`, ceo.js): Reinaldo's landing. The greeting
 * by the São Paulo hour, the four KPIs, "As 3 ações de hoje" — each a sentence of this locale from
 * the API's numbers (W82) and a button to the screen it names (`to`, already filtered: the trail by
 * `?parceiro=`, the pipeline by `?etapa=`, W83) —, the CBS countdown (`MCountdown`), then "Próximas
 * reuniões", "Indicações e SLA de 24h úteis" (5, "e mais N") and "Pipeline por etapa". Every figure
 * is the API's (`GET briefing`), the same payload as the 07:00 e-mail; nothing is summed here.
 */
const { locale, t } = useI18n();
const userStore = useUserStore();
const { formatShortRange } = useFiscalFormat();
const { stageLabel } = useLeadFormat();

/** The greeting and the date are read again every minute, so an open screen follows the clock (review S3). */
const CLOCK_TICK_MS = 60_000;

const briefing = ref<Briefing | null>(null);
const now = ref(new Date());
let clock: ReturnType<typeof setInterval> | null = null;
const loadFailed = ref(false);
const loading = ref(false);

const greeting = computed(() => {
  const name = userStore.user?.name ?? '';
  const hour = Number(
    new Intl.DateTimeFormat('en-GB', { hour: '2-digit', hourCycle: 'h23', timeZone: BRIEFING_TIME_ZONE }).format(
      now.value
    )
  );

  if (hour >= GREETING_HOURS.morning && hour < GREETING_HOURS.afternoon)
    return t('briefing.greeting.morning', { name });

  if (hour >= GREETING_HOURS.afternoon && hour < GREETING_HOURS.night)
    return t('briefing.greeting.afternoon', { name });

  return t('briefing.greeting.night', { name });
});

const today = computed(() =>
  new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    month: 'long',
    timeZone: BRIEFING_TIME_ZONE,
    weekday: 'long',
  }).format(now.value)
);

onMounted(onComponentMount);
onBeforeUnmount(onComponentUnmount);

// EVENTS
function onComponentMount() {
  clock = setInterval(onClockTick, CLOCK_TICK_MS);

  load();
}

function onComponentUnmount() {
  if (clock) clearInterval(clock);

  clock = null;
}

function onClockTick() {
  now.value = new Date();
}

// HELPERS
function load() {
  loading.value = true;
  loadFailed.value = false;

  GetBriefingService()
    .then((response) => (briefing.value = response.data))
    .catch(() => (loadFailed.value = true))
    .finally(() => (loading.value = false));
}

function formatCount(value: number): string {
  return new Intl.NumberFormat(locale.value).format(value);
}

function formatHours(value: number): string {
  return new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(value);
}

function formatRangeOrNoBase(range: BriefingRange): string {
  return range ? formatShortRange(range) : t('briefing.kpis.noBase');
}

function formatTime(at: string): string {
  return new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: '2-digit',
    timeZone: BRIEFING_TIME_ZONE,
    weekday: 'short',
  }).format(new Date(at));
}

/** The action's sentence in this locale, from the API's numbers (R25's copy, the demo's words). */
function sentenceOf(action: BriefingAction): string {
  switch (action.kind) {
    case BRIEFING_ACTION_KIND.SLA_BREACHED:
      return t('briefing.actions.sla_breached', {
        company: action.params.company,
        goal: action.params.goal_hours,
        hours: formatHours(action.params.hours),
        partner: action.params.partner,
      });
    case BRIEFING_ACTION_KIND.MEETINGS_TODAY:
      return t('briefing.actions.meetings_today', { count: action.params.count }, action.params.count);
    case BRIEFING_ACTION_KIND.PROPOSALS:
      return action.params.range_cents
        ? t(
            'briefing.actions.proposals',
            { count: action.params.count, range: formatShortRange(action.params.range_cents) },
            action.params.count
          )
        : t('briefing.actions.proposals_no_range', { count: action.params.count }, action.params.count);
    case BRIEFING_ACTION_KIND.PARTIAL_LEADS:
      return t('briefing.actions.partial_leads', { count: action.params.count }, action.params.count);
    case BRIEFING_ACTION_KIND.RADAR:
      return t(
        action.params.period === BRIEFING_RADAR_PERIOD.MONTH
          ? 'briefing.actions.radar_monthly'
          : 'briefing.actions.radar',
        {
          count: action.params.clients,
          range: formatShortRange(action.params.range_cents),
          title: action.params.title,
        },
        action.params.clients
      );
  }
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="briefing.title" />
    </template>

    <section class="bg-panel shadow-lift motion-enter flex flex-col gap-1.5 rounded-lg p-5">
      <h2
        class="text-heading text-xl font-semibold"
        data-testid="briefing-greeting"
      >
        {{ greeting }}
      </h2>

      <p class="text-body text-sm">{{ $t('briefing.lede', { day: today }) }}</p>

      <p class="text-muted text-xs">{{ $t('briefing.mailNote') }}</p>
    </section>

    <div
      v-if="loading && !briefing"
      class="flex flex-col gap-3"
      data-testid="briefing-loading"
    >
      <Skeleton height="7rem" />

      <Skeleton height="20rem" />
    </div>

    <div
      v-else-if="loadFailed && !briefing"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      data-testid="briefing-failed"
      role="alert"
    >
      <p class="text-body">{{ $t('briefing.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('briefing.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="briefing">
      <section
        :aria-label="$t('briefing.kpis.label')"
        :class="['grid grid-cols-1 gap-3 sm:grid-cols-2', briefing.referrals ? 'xl:grid-cols-4' : 'xl:grid-cols-3']"
      >
        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          data-kpi="leads"
          data-testid="briefing-kpi"
          :data-value="briefing.leads.last_7_days"
        >
          <span class="text-muted text-sm">{{ $t('briefing.kpis.leads') }}</span>

          <strong
            v-count-up
            class="text-heading text-2xl font-semibold tabular-nums"
          >
            {{ formatCount(briefing.leads.last_7_days) }}
          </strong>

          <span class="text-muted text-xs">
            {{ $t('briefing.kpis.leadsSub', { count: formatCount(briefing.leads.last_24_hours) }) }}
          </span>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          data-kpi="meetings"
          data-testid="briefing-kpi"
          :data-value="briefing.meetings.count"
          :style="{ '--reveal-index': 1 }"
        >
          <span class="text-muted text-sm">{{ $t('briefing.kpis.meetings') }}</span>

          <strong
            v-count-up
            class="text-heading text-2xl font-semibold tabular-nums"
          >
            {{ formatCount(briefing.meetings.count) }}
          </strong>

          <span class="text-muted text-xs">
            {{ $t('briefing.kpis.meetingsSub', { count: formatCount(briefing.meetings.today) }) }}
          </span>
        </div>

        <div
          v-if="briefing.referrals"
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          data-kpi="referrals"
          data-testid="briefing-kpi"
          :data-value="briefing.referrals.count"
          :style="{ '--reveal-index': 2 }"
        >
          <span class="text-muted text-sm">{{ $t('briefing.kpis.referrals') }}</span>

          <strong
            v-count-up
            class="text-heading text-2xl font-semibold tabular-nums"
          >
            {{ formatCount(briefing.referrals.count) }}
          </strong>

          <span class="text-muted text-xs">
            {{
              $t('briefing.kpis.referralsSub', {
                breached: formatCount(briefing.referrals.by_state.estourado),
                warning: formatCount(briefing.referrals.by_state.atencao),
              })
            }}
          </span>
        </div>

        <div
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-1 rounded-lg p-4"
          data-kpi="pipeline"
          data-testid="briefing-kpi"
          :data-value="briefing.pipeline.count"
          :style="{ '--reveal-index': 3 }"
        >
          <span class="text-muted text-sm">{{ $t('briefing.kpis.pipeline') }}</span>

          <strong class="text-heading text-xl font-semibold break-words tabular-nums">
            {{ formatRangeOrNoBase(briefing.pipeline.range_cents) }}
          </strong>

          <span class="text-muted text-xs">
            {{ $t('briefing.kpis.pipelineSub', { count: formatCount(briefing.pipeline.count) }) }}
          </span>
        </div>
      </section>

      <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <section
          aria-labelledby="briefing-actions-title"
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-4 rounded-lg p-5"
          data-testid="briefing-actions"
        >
          <h2
            id="briefing-actions-title"
            class="text-heading text-base font-semibold"
          >
            {{ $t('briefing.actions.title') }}
          </h2>

          <p
            v-if="briefing.actions.length === 0"
            class="text-muted text-sm"
            data-testid="briefing-actions-empty"
          >
            {{ $t('briefing.actions.empty') }}
          </p>

          <ol
            v-else
            class="flex flex-col gap-3"
          >
            <li
              v-for="(action, index) in briefing.actions"
              class="border-line motion-enter flex flex-col gap-3 border-t pt-3 first:border-t-0 first:pt-0 sm:flex-row sm:items-center sm:justify-between"
              data-testid="briefing-action"
              :key="action.kind"
              :data-kind="action.kind"
              :style="{ '--reveal-index': index }"
            >
              <span
                class="text-body flex min-w-0 items-start gap-3 text-sm"
                data-testid="briefing-action-text"
              >
                <span
                  aria-hidden="true"
                  class="bg-primary-100 text-primary-950 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                >
                  {{ index + 1 }}
                </span>

                {{ sentenceOf(action) }}
              </span>

              <RouterLink
                v-slot="{ navigate }"
                custom
                :to="action.to"
              >
                <Button
                  class="shrink-0"
                  data-testid="briefing-action-go"
                  icon="pi pi-arrow-right"
                  iconPos="right"
                  severity="secondary"
                  size="small"
                  :data-to="action.to"
                  :label="$t(`briefing.actions.go.${action.kind}`)"
                  @click="navigate"
                />
              </RouterLink>
            </li>
          </ol>
        </section>

        <MCountdown />
      </div>

      <div :class="['grid grid-cols-1 gap-3', briefing.referrals ? 'xl:grid-cols-3' : 'xl:grid-cols-2']">
        <section
          aria-labelledby="briefing-meetings-title"
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-3 rounded-lg p-5"
          data-testid="briefing-meetings"
        >
          <h2
            id="briefing-meetings-title"
            class="text-heading text-base font-semibold"
          >
            {{ $t('briefing.meetings.title') }}
          </h2>

          <p
            v-if="briefing.meetings.next.length === 0"
            class="text-muted text-sm"
          >
            {{ $t('briefing.meetings.empty') }}
          </p>

          <ul
            v-else
            class="flex flex-col gap-2 text-sm"
          >
            <li
              v-for="meeting in briefing.meetings.next"
              class="flex min-w-0 flex-wrap items-baseline gap-x-2"
              data-testid="briefing-meeting"
              :key="`${meeting.lead_id}-${meeting.starts_at}`"
            >
              <time
                class="text-heading font-semibold tabular-nums"
                :datetime="meeting.starts_at"
              >
                {{ formatTime(meeting.starts_at) }}
              </time>

              <RouterLink
                class="text-heading min-w-0 truncate font-medium hover:underline"
                :to="{ name: getI18nRouteName(PIPELINE_ROUTE_NAMES.lead), params: { id: meeting.lead_id } }"
              >
                {{ meeting.company ?? $t('briefing.meetings.anonymous') }}
              </RouterLink>

              <span class="text-muted">{{ meeting.specialist }}</span>
            </li>
          </ul>

          <p
            v-if="briefing.meetings.count > briefing.meetings.next.length"
            class="text-muted text-xs"
          >
            {{ $t('briefing.meetings.more', { count: briefing.meetings.count - briefing.meetings.next.length }) }}
          </p>
        </section>

        <section
          v-if="briefing.referrals"
          aria-labelledby="briefing-referrals-title"
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-3 rounded-lg p-5"
          data-testid="briefing-referrals"
          :style="{ '--reveal-index': 1 }"
        >
          <h2
            id="briefing-referrals-title"
            class="text-heading text-base font-semibold"
          >
            {{ $t('briefing.referrals.title', { goal: briefing.referrals.goal_hours }) }}
          </h2>

          <p
            v-if="briefing.referrals.top.length === 0"
            class="text-muted text-sm"
          >
            {{ $t('briefing.referrals.empty') }}
          </p>

          <ul
            v-else
            class="flex flex-col gap-2.5 text-sm"
          >
            <li
              v-for="referral in briefing.referrals.top"
              class="flex min-w-0 flex-col gap-1"
              data-testid="briefing-referral"
              :key="referral.id"
            >
              <span class="min-w-0">
                <strong class="text-heading font-semibold">
                  {{ referral.company ?? $t('briefing.meetings.anonymous') }}
                </strong>

                <span class="text-muted"> → {{ referral.partner.name }}</span>
              </span>

              <MSlaChip :sla="{ hours: referral.hours, state: referral.state }" />
            </li>
          </ul>

          <p
            v-if="briefing.referrals.more > 0"
            class="text-muted text-xs"
            data-testid="briefing-referrals-more"
          >
            {{ $t('briefing.referrals.more', { count: briefing.referrals.more }) }}
          </p>
        </section>

        <section
          aria-labelledby="briefing-stages-title"
          class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-3 rounded-lg p-5"
          data-testid="briefing-stages"
          :style="{ '--reveal-index': 2 }"
        >
          <h2
            id="briefing-stages-title"
            class="text-heading text-base font-semibold"
          >
            {{ $t('briefing.stages.title') }}
          </h2>

          <ul class="flex flex-col gap-2 text-sm">
            <li
              v-for="stage in briefing.pipeline.stages"
              class="border-line flex min-w-0 flex-wrap items-baseline justify-between gap-x-3 border-t pt-2 first:border-t-0 first:pt-0"
              data-testid="briefing-stage"
              :key="stage.stage"
              :data-stage="stage.stage"
            >
              <span class="text-heading font-semibold">{{ stageLabel(stage.stage) }}</span>

              <span class="text-body tabular-nums">
                {{ $t('briefing.stages.count', { count: formatCount(stage.count) }, stage.count) }}
                ·
                {{
                  stage.range_cents
                    ? $t('briefing.stages.perYear', { range: formatShortRange(stage.range_cents) })
                    : $t('briefing.kpis.noBase')
                }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </TheLayout>
</template>
