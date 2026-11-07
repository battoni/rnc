import { describe, expect, it } from 'vitest';
import type { Idea } from '../../../interfaces';
import { mountWithPlugins } from '@/test/mount';
import { IDEA_AREA, IDEA_STATUS, IDEA_TYPE } from '../../../enums';
import AIdeaStatusTag from '../../atoms/AIdeaStatusTag/AIdeaStatusTag.vue';
import AIdeaTypeBadge from '../../atoms/AIdeaTypeBadge/AIdeaTypeBadge.vue';
import AIdeaVoteButton from '../../atoms/AIdeaVoteButton/AIdeaVoteButton.vue';
import MIdeaCard from './MIdeaCard.vue';

/*
 * The app auto-imports these children (`unplugin-vue-components`); the unit-test harness does not,
 * so an unregistered child renders as an unknown element and every assertion about its output
 * quietly passes over nothing. They are registered by hand here for that reason.
 *
 * Text is asserted in ENGLISH: the harness boots i18n on `VITE_DEFAULT_LANGUAGE`, which is `en`,
 * and a spec that asserts a translated string has to say which language it means.
 */
const GLOBAL_COMPONENTS = { AIdeaStatusTag, AIdeaTypeBadge, AIdeaVoteButton };

function makeIdea(overrides: Partial<Idea> = {}): Idea {
  return {
    id: 1,
    title: 'Duplicate a block by dragging with Alt',
    description: 'Almost every block I place is the same as one already there.',
    type: IDEA_TYPE.IMPROVEMENT,
    area: IDEA_AREA.HOME,
    status: IDEA_STATUS.PLANNED,
    adminNote: null,
    votesCount: 4,
    commentsCount: 0,
    voted: false,
    canEdit: false,
    hidden: false,
    mergedIntoId: null,
    deliveredAt: null,
    createdAt: '2026-09-01T10:00:00+00:00',
    author: { id: 9, name: 'Marina' },
    attachment: null,
    ...overrides,
  };
}

describe('MIdeaCard', () => {
  it('shows the title, the support count and who wrote it', () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea() },
      global: { components: GLOBAL_COMPONENTS },
    });

    expect(wrapper.text()).toContain('Duplicate a block');
    expect(wrapper.text()).toContain('4');
    expect(wrapper.text()).toContain('Marina');
  });

  /* The row outlives the account: `user_id` is nullOnDelete precisely so the board keeps it. */
  it('names a removed account rather than showing a blank byline', () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea({ author: null }) },
      global: { components: GLOBAL_COMPONENTS },
    });

    expect(wrapper.text()).toContain('Removed account');
  });

  it('shows the maintainer answer on the card when there is one', () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea({ adminNote: 'It is already queued.' }) },
      global: { components: GLOBAL_COMPONENTS },
    });

    expect(wrapper.text()).toContain('It is already queued.');
  });

  /*
   * `canEdit` comes from the API, which is also what enforces it. The card must not recompute the
   * rule — two copies is how a visible button and a 403 start disagreeing.
   */
  it('offers editing only when the API says the viewer may', () => {
    const locked = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea({ canEdit: false }) },
      global: { components: GLOBAL_COMPONENTS },
    });
    expect(locked.find('[icon="pi pi-pencil"]').exists()).toBe(false);

    const editable = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea({ canEdit: true }) },
      global: { components: GLOBAL_COMPONENTS },
    });
    expect(editable.find('[icon="pi pi-pencil"]').exists()).toBe(true);
  });

  it('passes the idea up when support is toggled', async () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea() },
      global: { components: GLOBAL_COMPONENTS },
    });

    await wrapper.find('button[aria-pressed]').trigger('click');

    expect(wrapper.emitted('onToggleVote')?.[0]?.[0]).toMatchObject({ id: 1 });
  });

  it('asks to open the idea when the title is clicked', async () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea() },
      global: { components: GLOBAL_COMPONENTS },
    });

    const TITLE = wrapper.findAll('button').find((button) => button.text().includes('Duplicate'));
    await TITLE?.trigger('click');

    expect(wrapper.emitted('onOpen')?.[0]).toEqual([1]);
  });

  /*
   * The title is a hand-written button one line of text tall (~24px). On a touch screen its hit
   * area grows with `touch-target`, and the row keeps 12px to the pencil beside it (celer-06).
   */
  it('gives the clickable title a thumb-sized target, 12px from the controls beside it', () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea({ canEdit: true }) },
      global: { components: GLOBAL_COMPONENTS },
    });

    const TITLE = wrapper.get('[data-testid="idea-open"]');

    expect(TITLE.classes()).toContain('touch-target');
    expect(TITLE.element.parentElement?.classList.contains('gap-3')).toBe(true);
  });

  it('marks a hidden idea, for the moderator who is the only one seeing it', () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: { idea: makeIdea({ hidden: true }) },
      global: { components: GLOBAL_COMPONENTS },
    });

    expect(wrapper.text()).toContain('hidden');
  });

  it('says an idea carries an image without loading it into the list', () => {
    const wrapper = mountWithPlugins(MIdeaCard, {
      props: {
        idea: makeIdea({ attachment: { url: 'https://example.test/a.png', mime: 'image/png', size: 1024 } }),
      },
      global: { components: GLOBAL_COMPONENTS },
    });

    expect(wrapper.text()).toContain('with image');
    expect(wrapper.find('img').exists()).toBe(false);
  });
});
