<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useCountdown } from '@Composables';
import { CBS_START } from '../../../constants';

/*
 * "Reforma: CBS em vigor em" (rnc-3 R26; the demo's `briefing-countdown`): days, hours, minutes and
 * seconds to 01/01/2027 00:00 in São Paulo, ticking every second by the wall clock (`useCountdown`
 * — a suspended tab comes back right, not behind), with the dated caption of facts.md §2 and its
 * note that entities asked to postpone the CBS to 2028. From that instant it reads "CBS em vigor
 * desde 01/01/2027" and stops.
 */
const SECONDS_PER_DAY = 86400;
const SECONDS_PER_HOUR = 3600;
const SECONDS_PER_MINUTE = 60;
const HOURS_PER_DAY = 24;
const MILLISECONDS_PER_SECOND = 1000;

const { isRunning, remainingSeconds, start } = useCountdown();

const parts = computed(() => [
  { key: 'days', value: Math.floor(remainingSeconds.value / SECONDS_PER_DAY) },
  { key: 'hours', value: Math.floor(remainingSeconds.value / SECONDS_PER_HOUR) % HOURS_PER_DAY },
  { key: 'minutes', value: Math.floor(remainingSeconds.value / SECONDS_PER_MINUTE) % SECONDS_PER_MINUTE },
  { key: 'seconds', value: remainingSeconds.value % SECONDS_PER_MINUTE },
]);

onMounted(onComponentMount);

// HELPERS
function padded(value: number): string {
  return String(value).padStart(2, '0');
}

// EVENTS
function onComponentMount() {
  start(Math.max(0, (CBS_START.getTime() - Date.now()) / MILLISECONDS_PER_SECOND));
}
</script>

<template>
  <section
    aria-labelledby="briefing-countdown-title"
    class="bg-panel shadow-lift motion-enter flex min-w-0 flex-col gap-4 rounded-lg p-5"
    data-testid="briefing-countdown"
  >
    <h2
      id="briefing-countdown-title"
      class="text-heading text-base font-semibold"
    >
      {{ isRunning ? $t('briefing.countdown.title') : $t('briefing.countdown.started') }}
    </h2>

    <ol
      v-if="isRunning"
      class="grid grid-cols-4 gap-2"
      :aria-label="$t('briefing.countdown.ariaLabel')"
    >
      <li
        v-for="part in parts"
        class="bg-panel-muted flex min-w-0 flex-col items-center gap-0.5 rounded-md px-1 py-3"
        :key="part.key"
        :data-part="part.key"
      >
        <strong class="text-heading text-2xl font-semibold tabular-nums sm:text-3xl">{{ padded(part.value) }}</strong>

        <span class="text-muted text-xs">{{ $t(`briefing.countdown.${part.key}`) }}</span>
      </li>
    </ol>

    <div class="flex flex-col gap-1.5 text-sm">
      <p
        class="text-body"
        data-testid="countdown-caption"
      >
        {{ $t('briefing.countdown.caption') }}
      </p>

      <p class="text-muted">{{ $t('briefing.countdown.postponed') }}</p>
    </div>
  </section>
</template>
