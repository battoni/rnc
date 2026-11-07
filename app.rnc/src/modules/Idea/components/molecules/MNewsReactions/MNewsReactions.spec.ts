import { afterEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MNewsReactions from './MNewsReactions.vue';

const REACTIONS = { clap: 4, heart: 2, rocket: 0 };
const MINE = { clap: 1, heart: 0, rocket: 0 };

function mount(props = {}) {
  return mountWithPlugins(MNewsReactions, { props: { myReactions: MINE, reactions: REACTIONS, ...props } });
}

const ORIGINAL_MATCH_MEDIA = window.matchMedia;

function preferReducedMotion() {
  window.matchMedia = vi.fn((query: string) => ({
    ...ORIGINAL_MATCH_MEDIA(query),
    matches: query.includes('prefers-reduced-motion: reduce'),
  })) as unknown as typeof window.matchMedia;
}

afterEach(() => {
  window.matchMedia = ORIGINAL_MATCH_MEDIA;
});

describe('MNewsReactions', () => {
  it('shows the room, not just the reader', () => {
    const wrapper = mount();

    expect(wrapper.find('[data-testid="news-react-clap"]').text()).toContain('4');
    expect(wrapper.find('[data-testid="news-react-heart"]').text()).toContain('2');
    expect(wrapper.find('[data-testid="news-react-rocket"]').text()).toContain('0');
  });

  /*
   * Applause that waits for the server to agree does not feel like applause. The count moves on the
   * click; the props catch up when the response lands.
   */
  it('moves the count on the click, before any response', async () => {
    const wrapper = mount();

    await wrapper.find('[data-testid="news-react-clap"]').trigger('click');

    expect(wrapper.find('[data-testid="news-react-clap"]').text()).toContain('5');
    // The emit carries the kind AND the callback that repays this particular click.
    expect(wrapper.emitted('onReact')?.[0]?.[0]).toBe('clap');
    expect(typeof wrapper.emitted('onReact')?.[0]?.[1]).toBe('function');
  });

  /*
   * A vote is one per person. This is applause — clapping twice is the entire point.
   *
   * This covers the COMPONENT's arithmetic, not the app-wide double-click guard: that guard is a
   * document listener the unit harness never installs, so removing `data-allow-rapid-click` would
   * leave this green. The e2e in `e2e/ideas/news.spec.ts` is what holds the hatch in place.
   */
  it('lets the same button be clicked over and over', async () => {
    const wrapper = mount();
    const button = wrapper.find('[data-testid="news-react-clap"]');

    await button.trigger('click');
    await button.trigger('click');
    await button.trigger('click');

    expect(button.text()).toContain('7');
    expect(wrapper.emitted('onReact')).toHaveLength(3);
  });

  it('throws confetti of the kind that was clicked, and of no other', async () => {
    const wrapper = mount();

    await wrapper.find('[data-testid="news-react-rocket"]').trigger('click');

    const particles = wrapper.findAll('.news-burst');
    expect(particles.length).toBeGreaterThan(0);
    particles.forEach((particle) => expect(particle.text()).toBe('\u{1F680}'));
  });

  /* Every particle removes itself when its own animation ends — no timer to leak or get out of sync. */
  it('clears each particle when its animation ends', async () => {
    const wrapper = mount();

    await wrapper.find('[data-testid="news-react-clap"]').trigger('click');
    const before = wrapper.findAll('.news-burst').length;

    await wrapper.findAll('.news-burst')[0]?.trigger('animationend');

    expect(wrapper.findAll('.news-burst')).toHaveLength(before - 1);
  });

  /*
   * Spent is PRESSED IN and unspent is RAISED — the theme's relief vocabulary, not an outline. The
   * classes are asserted because they are the only thing that carries the state: a border was what
   * this used to say it with, and the design system retires borders around surfaces.
   */
  it('marks the kinds this reader has already spent', () => {
    const wrapper = mount();

    const spent = wrapper.find('[data-testid="news-react-clap"]').classes();
    const unspent = wrapper.find('[data-testid="news-react-heart"]').classes();

    expect(spent).toContain('shadow-sink');
    expect(spent).not.toContain('shadow-lift');
    expect(unspent).toContain('shadow-lift');
    expect(unspent).not.toContain('shadow-sink');
  });

  /* The retired outline, pinned so nobody reintroduces it while "fixing" the state cue. */
  it('never draws a box around itself', () => {
    const wrapper = mount();

    wrapper.findAll('button').forEach((button) => {
      expect(button.classes()).not.toContain('border');
      expect(button.classes()).not.toContain('border-primary-500');
    });
  });

  /*
   * Under reduced motion the CSS hides the burst, so its `animationend` never fires and the node
   * that waits for it is never removed: seven spans per click, piling up until unmount. The script
   * refuses to create them at all — the count still moves, which is the whole gesture.
   */
  it('throws no confetti at all when the reader asked for reduced motion', async () => {
    preferReducedMotion();

    const wrapper = mount();
    const button = wrapper.find('[data-testid="news-react-clap"]');

    await button.trigger('click');
    await button.trigger('click');

    expect(wrapper.findAll('.news-burst')).toHaveLength(0);
    expect(button.text()).toContain('6');
  });

  /*
   * Hand-written buttons (celer-06): the hit area grows on a touch screen, and 12px between them
   * keeps the grown areas from overlapping — the later sibling would win the overlap.
   */
  it('gives each button a thumb-sized target, 12px from the next', () => {
    const wrapper = mount();

    expect(wrapper.classes()).toContain('gap-3');
    wrapper.findAll('button').forEach((button) => expect(button.classes()).toContain('touch-target'));
  });

  /*
   * The spent count is read as text, and the primary ramp does not flip: primary-700 on the light
   * panel measured 1.9:1. The relief carries the state; the count only has to be readable.
   */
  it('never paints the spent count in a primary shade', () => {
    const wrapper = mount();

    wrapper.findAll('button span').forEach((span) => {
      expect(span.classes().some((name) => name.startsWith('text-primary-'))).toBe(false);
    });
  });

  it('stays silent while disabled', async () => {
    const wrapper = mount({ disabled: true });

    await wrapper.find('[data-testid="news-react-clap"]').trigger('click');

    expect(wrapper.emitted('onReact')).toBeUndefined();
    expect(wrapper.findAll('.news-burst')).toHaveLength(0);
  });

  /* An entry nobody has reacted to yet must render zeroes, not blanks. */
  it('renders without any counts at all', () => {
    const wrapper = mountWithPlugins(MNewsReactions);

    expect(wrapper.find('[data-testid="news-react-clap"]').text()).toContain('0');
  });

  /*
   * Each click's loan is repaid by ITS OWN request, through the callback the emit handed out.
   * Clearing them all on the first response made three fast claps fall from 3 to 1 before climbing
   * back; repaying on prop identity meant any unrelated write to the entry repaid one.
   */
  it('repays one loan per settled request, not all of them', async () => {
    const wrapper = mount();
    const button = wrapper.find('[data-testid="news-react-clap"]');

    await button.trigger('click');
    await button.trigger('click');
    await button.trigger('click');
    expect(button.text()).toContain('7');

    // The first request settles, and the parent patches the counts it now knows about.
    const settleFirst = wrapper.emitted('onReact')?.[0]?.[1] as () => void;
    settleFirst();
    await wrapper.setProps({ reactions: { ...REACTIONS, clap: 5 } });

    expect(button.text()).toContain('7');
  });

  /*
   * The bug this design replaced: opening an article you had just clapped gave the component a new
   * `reactions` object from the mark-read response, which repaid a clap that was still in flight.
   */
  it('repays nothing when an unrelated write changes the counts', async () => {
    const wrapper = mount();
    const button = wrapper.find('[data-testid="news-react-clap"]');

    await button.trigger('click');
    expect(button.text()).toContain('5');

    // Same counts, new identity — what `replaceNewsEntry` hands back after a mark-read.
    await wrapper.setProps({ reactions: { ...REACTIONS } });

    expect(button.text()).toContain('5');
  });

  /*
   * A failed request settles too. Without repayment the reader keeps looking at applause the
   * server never recorded.
   */
  it('gives the loan back when the request failed', async () => {
    const wrapper = mount();
    const button = wrapper.find('[data-testid="news-react-clap"]');

    await button.trigger('click');
    expect(button.text()).toContain('5');

    const settle = wrapper.emitted('onReact')?.[0]?.[1] as () => void;
    settle();
    await wrapper.vm.$nextTick();

    expect(button.text()).toContain('4');
  });
});
