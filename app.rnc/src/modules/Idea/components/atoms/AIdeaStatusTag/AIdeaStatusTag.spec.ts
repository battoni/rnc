import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import { IDEA_STATUS } from '../../../enums';
import AIdeaStatusTag from './AIdeaStatusTag.vue';

describe('AIdeaStatusTag', () => {
  /*
   * The brief asks for a product that does not judge. Painting a declined idea in error red turns
   * a scoping decision into a verdict on whoever wrote it, so `not_now` uses the `contrast` ramp
   * and this spec is what keeps it there.
   */
  it('never paints "not now" as an error', () => {
    const wrapper = mountWithPlugins(AIdeaStatusTag, { props: { status: IDEA_STATUS.NOT_NOW } });

    expect(wrapper.classes().some((name) => name.includes('danger'))).toBe(false);
    expect(wrapper.classes().some((name) => name.includes('contrast'))).toBe(true);
  });

  it('marks a delivered idea with the success palette', () => {
    const wrapper = mountWithPlugins(AIdeaStatusTag, { props: { status: IDEA_STATUS.DELIVERED } });

    expect(wrapper.classes().some((name) => name.includes('success'))).toBe(true);
  });

  /* An unanswered idea has no hue: a colour would make "nobody looked yet" a chosen state. */
  it('leaves a received idea on the muted panel, with no hue of its own', () => {
    const wrapper = mountWithPlugins(AIdeaStatusTag, { props: { status: IDEA_STATUS.RECEIVED } });

    expect(wrapper.classes()).toContain('bg-panel-muted');
  });

  it('translates every status rather than printing its key', () => {
    Object.values(IDEA_STATUS).forEach((status) => {
      const wrapper = mountWithPlugins(AIdeaStatusTag, { props: { status } });

      expect(wrapper.text()).not.toContain('ideas.status.');
      expect(wrapper.text().trim().length).toBeGreaterThan(0);
    });
  });
});
