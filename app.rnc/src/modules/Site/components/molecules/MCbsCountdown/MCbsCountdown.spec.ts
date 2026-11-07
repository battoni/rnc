import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MCbsCountdown from './MCbsCountdown.vue';

/* R5 (rnc-1 F2): the landing counts down to 01/01/2027, when the CBS is charged in full. */
function partValues(wrapper: ReturnType<typeof mountWithPlugins>) {
  return Object.fromEntries(
    wrapper.findAll('[data-part]').map((part) => [part.attributes('data-part'), part.find('strong').text()])
  );
}

afterEach(() => {
  vi.useRealTimers();
});

describe('MCbsCountdown', () => {
  it('shows the days, hours, minutes and seconds left until 01/01/2027', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 10, 7, 11, 30, 15));
    const wrapper = mountWithPlugins(MCbsCountdown);

    await nextTick();

    expect(partValues(wrapper)).toEqual({ days: '54', hours: '12', minutes: '29', seconds: '45' });
  });

  it('ticks by the wall clock', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 11, 31, 23, 59, 0));
    const wrapper = mountWithPlugins(MCbsCountdown);

    vi.setSystemTime(new Date(2026, 11, 31, 23, 59, 50));
    await vi.advanceTimersByTimeAsync(1000);

    expect(partValues(wrapper)).toMatchObject({ days: '00', minutes: '00', seconds: '09' });
  });

  it('says the CBS is charged once the date has passed', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2027, 0, 2));
    const wrapper = mountWithPlugins(MCbsCountdown);

    await nextTick();

    expect(wrapper.findAll('[data-part]')).toHaveLength(0);
    expect(wrapper.text()).toContain('CBS has been charged since 01/01/2027.');
  });
});
