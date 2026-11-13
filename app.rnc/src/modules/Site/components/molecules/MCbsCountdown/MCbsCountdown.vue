<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useCountdown } from '@Composables';
import { CBS_START } from '../../../constants';

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
  <div
    class="flex flex-col gap-3"
    data-testid="countdown"
  >
    <span class="text-primary-200 text-xs font-semibold tracking-widest uppercase">
      {{ $t('site.countdown.label') }}
    </span>

    <ol
      v-if="isRunning"
      class="grid grid-cols-4 gap-2 sm:gap-3"
      :aria-label="$t('site.countdown.ariaLabel')"
    >
      <li
        v-for="part in parts"
        class="bg-primary-700/70 ring-primary-300/20 flex flex-col items-center rounded-lg px-2 py-3 ring-1 sm:px-4"
        :key="part.key"
        :data-part="part.key"
      >
        <strong class="text-primary-contrast text-2xl font-bold tabular-nums sm:text-4xl">
          {{ padded(part.value) }}
        </strong>

        <span class="text-primary-200 text-xs">
          {{ $t(`site.countdown.${part.key}`) }}
        </span>
      </li>
    </ol>

    <p
      v-else
      class="text-primary-contrast text-lg font-semibold"
    >
      {{ $t('site.countdown.started') }}
    </p>
  </div>
</template>
