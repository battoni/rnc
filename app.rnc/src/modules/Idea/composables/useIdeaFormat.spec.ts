import { defineComponent } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import useIdeaFormat from './useIdeaFormat';

/*
 * Runs in São Paulo on purpose: under UTC the instant's month and the local month agree, and a key
 * cut from the ISO string would pass. The API sends instants in UTC; the heading is formatted on the
 * reader's clock, so the key has to be cut on the same clock or an entry lands under the wrong
 * heading. (Harness locale is `en`.)
 */
beforeEach(() => vi.stubEnv('TZ', 'America/Sao_Paulo'));

afterEach(() => vi.unstubAllEnvs());

function useFormat() {
  let format!: ReturnType<typeof useIdeaFormat>;

  mountWithPlugins(
    defineComponent({
      setup() {
        format = useIdeaFormat();

        return () => null;
      },
    })
  );

  return format;
}

describe('useIdeaFormat — monthKey', () => {
  it('groups an entry under the month the reader sees, not the month in UTC', () => {
    // 01:00 on 1 October in UTC is 22:00 on 30 September in São Paulo.
    const LATE_SEPTEMBER = '2026-10-01T01:00:00Z';
    const { formatMonth, monthKey } = useFormat();

    expect(formatMonth(LATE_SEPTEMBER)).toBe('September 2026');
    expect(monthKey(LATE_SEPTEMBER)).toBe('2026-09');
  });

  it('gives nothing a key of nothing', () => {
    expect(useFormat().monthKey(null)).toBe('');
  });
});
