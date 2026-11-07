import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import AIdeaVoteButton from './AIdeaVoteButton.vue';

describe('AIdeaVoteButton', () => {
  it('shows the count even at zero, so the card invites the first supporter', () => {
    const wrapper = mountWithPlugins(AIdeaVoteButton, { props: { count: 0, voted: false } });

    expect(wrapper.text()).toContain('0');
  });

  it('reports the supported state to assistive technology', () => {
    const wrapper = mountWithPlugins(AIdeaVoteButton, { props: { count: 3, voted: true } });

    expect(wrapper.attributes('aria-pressed')).toBe('true');
  });

  /*
   * Without a name of its own the button is announced by its content alone — "3, toggle button" —
   * which says neither what pressing it does nor what the number counts. The name stays the same in
   * both states: `aria-pressed` already says which way it is set. (Harness locale is `en`.)
   */
  it('has a name that says what it does and what the number counts', () => {
    const unsupported = mountWithPlugins(AIdeaVoteButton, { props: { count: 3, voted: false } });
    const supported = mountWithPlugins(AIdeaVoteButton, { props: { count: 1, voted: true } });

    expect(unsupported.attributes('aria-label')).toBe('Support this idea (3 supporters)');
    expect(supported.attributes('aria-label')).toBe('Support this idea (1 supporter)');
  });

  /*
   * The filled brand button takes `text-primary-contrast`, never a raw ink shade: ink on
   * terracotta measures 3.08:1, and the token exists to prevent exactly that.
   */
  it('wears the brand contrast token when supported', () => {
    const wrapper = mountWithPlugins(AIdeaVoteButton, { props: { count: 1, voted: true } });

    expect(wrapper.classes()).toContain('text-primary-contrast');
    expect(wrapper.classes().some((name) => name.startsWith('text-ink-'))).toBe(false);
  });

  it('emits once per click when it is free to', async () => {
    const wrapper = mountWithPlugins(AIdeaVoteButton, { props: { count: 1, voted: false } });

    await wrapper.trigger('click');

    expect(wrapper.emitted('onToggle')).toHaveLength(1);
  });

  it('stays silent while a vote is in flight', async () => {
    const wrapper = mountWithPlugins(AIdeaVoteButton, {
      props: { count: 1, voted: false, isBusy: true },
    });

    await wrapper.trigger('click');

    expect(wrapper.emitted('onToggle')).toBeUndefined();
  });
});
