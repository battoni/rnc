<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AgendaWeek } from '../../interfaces';
import { getI18nRouteName } from '@Helpers';
import { MEETING_TIME_ZONE } from '@DiagnosticModule';
import { useLeadFormat } from '../../composables';
import { PIPELINE_ROUTE_NAMES, STAGE_ACCENT_CLASSES } from '../../constants';
import { GetAgendaService } from '../../services';

/*
 * The team's agenda (rnc-1 R20): the working week, Monday to Friday in São Paulo, with every booked
 * meeting by day and hour; ◀ ▶ move a week, "Esta semana" comes back. Each meeting opens its lead.
 */
const NOON_OFFSET = 'T12:00:00-03:00';

const { locale, t } = useI18n();
const { formatTime, stageLabel } = useLeadFormat();

const agenda = ref<AgendaWeek | null>(null);
const hasFailed = ref(false);
const isLoading = ref(false);

const meetingCount = computed(() => agenda.value?.days.reduce((total, day) => total + day.meetings.length, 0) ?? 0);

const rangeLabel = computed(() =>
  agenda.value
    ? t('pipeline.agenda.range', { from: shortDate(agenda.value.week_start), to: shortDate(agenda.value.week_end) })
    : ''
);

onMounted(() => load(null));

// HELPERS
function load(week: string | null) {
  isLoading.value = true;
  hasFailed.value = false;

  GetAgendaService(week)
    .then((response) => (agenda.value = response.data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function dateOf(day: string): Date {
  return new Date(`${day}${NOON_OFFSET}`);
}

function shortDate(day: string): string {
  return new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    month: '2-digit',
    timeZone: MEETING_TIME_ZONE,
  }).format(dateOf(day));
}

function weekdayOf(day: string): string {
  return new Intl.DateTimeFormat(locale.value, { timeZone: MEETING_TIME_ZONE, weekday: 'long' }).format(dateOf(day));
}

// EVENTS
function onPrevious() {
  if (agenda.value) load(agenda.value.previous_week);
}

function onNext() {
  if (agenda.value) load(agenda.value.next_week);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="pipeline.agenda.title" />
    </template>

    <div class="flex min-w-0 flex-col gap-5">
      <p class="text-muted text-sm">{{ $t('pipeline.agenda.lede') }}</p>

      <div class="bg-panel shadow-lift flex items-center justify-between gap-3 rounded-lg p-3">
        <Button
          rounded
          text
          data-testid="agenda-prev"
          icon="pi pi-chevron-left"
          severity="secondary"
          :aria-label="$t('pipeline.agenda.previous')"
          :disabled="!agenda || isLoading"
          :title="$t('pipeline.agenda.previous')"
          @click="onPrevious"
        />

        <div class="flex min-w-0 flex-col items-center gap-0.5 text-center">
          <strong
            class="text-heading tabular-nums"
            data-testid="agenda-range"
          >
            {{ rangeLabel }}
          </strong>

          <span
            class="text-muted text-xs"
            data-testid="agenda-count"
          >
            {{ $t('pipeline.agenda.count', meetingCount) }}
          </span>

          <button
            class="touch-target text-primary-700 hover:text-primary-800 text-xs font-semibold underline-offset-4 hover:underline disabled:opacity-50"
            data-testid="agenda-today"
            type="button"
            :disabled="isLoading"
            @click="load(null)"
          >
            {{ $t('pipeline.agenda.today') }}
          </button>
        </div>

        <Button
          rounded
          text
          data-testid="agenda-next"
          icon="pi pi-chevron-right"
          severity="secondary"
          :aria-label="$t('pipeline.agenda.next')"
          :disabled="!agenda || isLoading"
          :title="$t('pipeline.agenda.next')"
          @click="onNext"
        />
      </div>

      <div
        v-if="hasFailed"
        class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
        role="alert"
      >
        <p class="text-body">{{ $t('pipeline.agenda.loadFailed') }}</p>

        <Button
          icon="pi pi-refresh"
          severity="secondary"
          :label="$t('pipeline.retry')"
          @click="load(agenda?.week_start ?? null)"
        />
      </div>

      <div
        v-else-if="!agenda"
        class="grid grid-cols-1 gap-4 md:grid-cols-5"
      >
        <Skeleton
          v-for="placeholder in 5"
          height="12rem"
          :key="placeholder"
        />
      </div>

      <div
        v-else
        class="grid grid-cols-1 gap-4 md:grid-cols-5"
        data-testid="agenda"
        :aria-busy="isLoading"
      >
        <section
          v-for="day in agenda.days"
          class="bg-panel-muted flex min-w-0 flex-col gap-3 rounded-lg p-3"
          data-testid="agenda-day"
          :key="day.date"
          :data-day="day.date"
        >
          <h2 class="flex items-baseline justify-between gap-2 px-0.5">
            <span class="text-heading text-sm font-semibold first-letter:uppercase">{{ weekdayOf(day.date) }}</span>

            <span class="text-muted text-xs tabular-nums">{{ shortDate(day.date) }}</span>
          </h2>

          <RouterLink
            v-for="meeting in day.meetings"
            class="bg-panel shadow-lift hover:shadow-lift-lg flex flex-col gap-1 rounded-md p-3 transition-shadow"
            data-testid="agenda-meeting"
            :key="meeting.id"
            :data-at="meeting.starts_at"
            :to="{ name: getI18nRouteName(PIPELINE_ROUTE_NAMES.lead), params: { id: meeting.lead_id } }"
          >
            <span class="text-primary-800 text-xs font-semibold tabular-nums">
              {{ formatTime(meeting.starts_at) }}–{{ formatTime(meeting.ends_at) }}
            </span>

            <strong class="text-heading text-sm break-words">{{ meeting.company ?? $t('pipeline.anonymous') }}</strong>

            <span class="text-muted text-xs">{{ meeting.specialist }}</span>

            <span class="text-muted flex items-center gap-1.5 text-xs">
              <span
                aria-hidden="true"
                :class="['size-1.5 rounded-full', STAGE_ACCENT_CLASSES[meeting.stage]]"
              />

              {{ stageLabel(meeting.stage) }}
            </span>
          </RouterLink>

          <p
            v-if="day.meetings.length === 0"
            class="text-muted px-0.5 py-2 text-xs"
          >
            {{ $t('pipeline.agenda.empty') }}
          </p>
        </section>
      </div>
    </div>
  </TheLayout>
</template>
