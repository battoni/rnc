<script setup lang="ts">
import { computed } from 'vue';
import type { BookingConfirmation, DiagnosticResult } from '../../../interfaces';
import { useMeetingTime } from '../../../composables';
import { DIAGNOSTIC_ROUTE_NAMES } from '../../../constants';

/*
 * The booking's confirmation (rnc-1 R16): when, with whom, how long, what the diagnostic found and
 * the link to the "Relatório de Impacto". One component for the form's last step and the chat's
 * last bubble (rnc-2 R12: "the same confirmation").
 */
const props = defineProps<{
  booking: BookingConfirmation;
  companyName: string | null;
  result: DiagnosticResult | null;
}>();

const { formatMeeting } = useMeetingTime();

const MINUTE_MS = 60_000;

const meetingMinutes = computed(() => {
  const { ends_at, starts_at } = props.booking.meeting;

  return Math.round((Date.parse(ends_at) - Date.parse(starts_at)) / MINUTE_MS);
});
</script>

<template>
  <div
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
          {{ formatMeeting(props.booking.meeting.starts_at) }}
        </strong>

        <span class="text-body">
          {{ $t('diagnostic.booking.confirmed.with', { specialist: props.booking.meeting.specialist }) }}
          <template v-if="props.booking.meeting.area">· {{ props.booking.meeting.area }}</template>
        </span>

        <span class="text-muted text-sm">
          {{ $t('diagnostic.booking.confirmed.length', { minutes: meetingMinutes }) }}
        </span>
      </div>
    </div>

    <p
      v-if="props.result"
      class="text-body"
    >
      {{
        $t('diagnostic.booking.confirmed.found', {
          company: props.result.company ?? props.companyName ?? '',
          opportunities: $t('diagnostic.result.opportunities', props.result.opportunities),
          risks: $t('diagnostic.result.risks', props.result.risks),
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
        :to="{ name: DIAGNOSTIC_ROUTE_NAMES.report, params: { token: props.booking.report_token } }"
      >
        <i
          aria-hidden="true"
          class="pi pi-file"
        />

        {{ $t('diagnostic.booking.confirmed.openReport') }}
      </RouterLink>
    </div>
  </div>
</template>
