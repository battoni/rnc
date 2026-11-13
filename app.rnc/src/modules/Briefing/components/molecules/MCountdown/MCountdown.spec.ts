import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MCountdown from './MCountdown.vue';

/*
 * The briefing's countdown (rnc-3 F9, R26, A23): to 01/01/2027 00:00 in São Paulo — not the
 * browser's midnight —, ticking every second, with the dated caption and the 2028 note; from that
 * instant, "CBS in force since 01/01/2027". The clock is fixed in UTC so a São Paulo instant is exact.
 */
function partValues(wrapper: ReturnType<typeof mountWithPlugins>) {
  return Object.fromEntries(
    wrapper.findAll('[data-part]').map((part) => [part.attributes('data-part'), part.find('strong').text()])
  );
}

afterEach(() => {
  vi.useRealTimers();
});

describe('MCountdown', () => {
  it('counts to 01/01/2027 00:00 in São Paulo (03:00 UTC)', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-07T13:30:15Z'));
    const wrapper = mountWithPlugins(MCountdown);

    await nextTick();

    expect(partValues(wrapper)).toEqual({ days: '54', hours: '13', minutes: '29', seconds: '45' });
    expect(wrapper.text()).toContain('Reform: CBS in force in');
  });

  // rnc-3 review S2: the very first render, before any tick or re-render, already counts down.
  it('shows the countdown, not "in force since", on its first frame', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-07T13:30:15Z'));
    const wrapper = mountWithPlugins(MCountdown);

    expect(wrapper.find('h2').text()).toBe('Reform: CBS in force in');
    expect(partValues(wrapper)).toEqual({ days: '54', hours: '13', minutes: '29', seconds: '45' });
  });

  it('aims at the same instant from a browser in another zone', async () => {
    const zone = process.env.TZ;

    process.env.TZ = 'Asia/Tokyo';
    vi.resetModules();

    try {
      const { CBS_START } = await import('../../../constants');

      expect(CBS_START.toISOString()).toBe('2027-01-01T03:00:00.000Z');
    } finally {
      process.env.TZ = zone;
    }
  });

  it('ticks every second by the wall clock', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2027-01-01T02:59:00Z'));
    const wrapper = mountWithPlugins(MCountdown);

    await vi.advanceTimersByTimeAsync(1000);
    expect(partValues(wrapper)).toEqual({ days: '00', hours: '00', minutes: '00', seconds: '59' });

    await vi.advanceTimersByTimeAsync(1000);
    expect(partValues(wrapper)).toMatchObject({ seconds: '58' });
  });

  it('shows the dated caption and the note on the 2028 request', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-07T13:30:15Z'));
    const wrapper = mountWithPlugins(MCountdown);

    await nextTick();

    expect(wrapper.get('[data-testid="countdown-caption"]').text()).toContain('read on 05/11/2026');
    expect(wrapper.text()).toContain('delay the CBS to 2028');
  });

  it('says the CBS is in force from 01/01/2027 00:00 in São Paulo', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2027-01-01T03:00:00Z'));
    const wrapper = mountWithPlugins(MCountdown);

    await nextTick();

    expect(wrapper.findAll('[data-part]')).toHaveLength(0);
    expect(wrapper.text()).toContain('CBS in force since 01/01/2027');
    expect(wrapper.text()).not.toContain('CBS in force in');
  });
});
