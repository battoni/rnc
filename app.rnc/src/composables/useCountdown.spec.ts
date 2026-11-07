import { effectScope } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useCountdown } from './useCountdown';

const START = new Date('2026-09-16T12:00:00Z').getTime();

/*
 * Only the interval and the clock are faked. A suspended phone is modelled by moving `Date.now`
 * WITHOUT running the interval — exactly what a browser does to a background tab.
 */
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
  vi.setSystemTime(START);
});

afterEach(() => vi.useRealTimers());

describe('useCountdown', () => {
  it('counts down by the second and stops at zero', () => {
    const { isRunning, remainingSeconds, start } = useCountdown();

    start(3);

    expect(remainingSeconds.value).toBe(3);
    expect(isRunning.value).toBe(true);

    vi.advanceTimersByTime(1000);

    expect(remainingSeconds.value).toBe(2);

    vi.advanceTimersByTime(2000);

    expect(remainingSeconds.value).toBe(0);
    expect(isRunning.value).toBe(false);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('answers by the wall clock after the timers were suspended', () => {
    const { remainingSeconds, start } = useCountdown();

    start(60);

    // Two minutes in another app: the clock moved, no tick ran. One tick is enough to catch up.
    vi.setSystemTime(START + 120_000);
    vi.advanceTimersByTime(1000);

    expect(remainingSeconds.value).toBe(0);
  });

  it('catches up the moment the tab becomes visible again, without waiting for a tick', () => {
    const { remainingSeconds, start } = useCountdown();

    start(60);

    vi.setSystemTime(START + 45_000);
    document.dispatchEvent(new Event('visibilitychange'));

    expect(remainingSeconds.value).toBe(15);
  });

  it('does not run at all for a zero cooldown', () => {
    const { isRunning, start } = useCountdown();

    start(0);

    expect(isRunning.value).toBe(false);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('restarts from the full value instead of stacking a second interval', () => {
    const { remainingSeconds, start } = useCountdown();

    start(10);
    vi.advanceTimersByTime(4000);
    start(10);

    expect(remainingSeconds.value).toBe(10);
    expect(vi.getTimerCount()).toBe(1);
  });

  it('stops when the owning scope is disposed', () => {
    const scope = effectScope();
    const countdown = scope.run(() => useCountdown());

    countdown?.start(30);
    scope.stop();

    expect(vi.getTimerCount()).toBe(0);

    vi.setSystemTime(START + 10_000);
    document.dispatchEvent(new Event('visibilitychange'));

    expect(countdown?.remainingSeconds.value).toBe(30);
  });
});
