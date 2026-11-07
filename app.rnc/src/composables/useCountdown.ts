import { computed, getCurrentScope, onScopeDispose, readonly, ref } from 'vue';

const SECOND_MS = 1000;

/**
 * A countdown measured against the wall clock, not by counting ticks.
 *
 * A timer is a request, not a promise: a phone suspends `setInterval` while the person is in
 * another app reading the code this countdown is guarding, and a countdown that decremented once
 * per tick came back still showing 42 seconds after two minutes away — "resend" locked long past
 * the time the screen promised. So the deadline is stored once, and every tick only asks how far
 * away it still is. The tick decides WHEN to look; `Date.now()` decides WHAT to show.
 *
 * Coming back to the tab is a moment to look as well (`visibilitychange`), so the number is right
 * on the first frame instead of one tick later.
 *
 * Stops by itself at zero and when the calling component's scope is disposed.
 */
function useCountdown() {
  let deadline = 0;
  let interval: ReturnType<typeof setInterval> | null = null;

  const remainingSeconds = ref(0);

  const isRunning = computed(() => remainingSeconds.value > 0);

  if (getCurrentScope()) onScopeDispose(stop);

  // HELPERS
  function secondsUntilDeadline(): number {
    return Math.max(0, Math.ceil((deadline - Date.now()) / SECOND_MS));
  }

  function stop() {
    if (interval) clearInterval(interval);

    interval = null;
    document.removeEventListener('visibilitychange', onTick);
  }

  // EVENTS
  function onTick() {
    remainingSeconds.value = secondsUntilDeadline();

    if (remainingSeconds.value === 0) stop();
  }

  function start(seconds: number) {
    stop();

    deadline = Date.now() + Math.max(0, seconds) * SECOND_MS;
    remainingSeconds.value = secondsUntilDeadline();

    if (remainingSeconds.value === 0) return;

    interval = setInterval(onTick, SECOND_MS);
    document.addEventListener('visibilitychange', onTick);
  }

  return { isRunning, remainingSeconds: readonly(remainingSeconds), start, stop };
}

export { useCountdown };
