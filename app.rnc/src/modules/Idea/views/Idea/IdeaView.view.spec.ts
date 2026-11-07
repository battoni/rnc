import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

/*
 * Ids are integers on the wire (lifeshifter-269) and `:id` is a string. This view is where the two
 * meet: it reads the param once with `toRouteId` and compares numbers from then on. Before, it
 * stringified both sides — `comment.author.id === String(user.id)` — which stops matching the
 * moment either side is a number, and the delete button vanishes from a person's own comment.
 */

const { getIdeaService, getIdeasService, mergeIdeaService, toastAdd } = vi.hoisted(() => ({
  getIdeaService: vi.fn(),
  getIdeasService: vi.fn(),
  mergeIdeaService: vi.fn(),
  toastAdd: vi.fn(),
}));

vi.mock('../../services', () => ({
  CreateIdeaCommentService: vi.fn(),
  CreateNewsService: vi.fn(),
  DeleteIdeaCommentService: vi.fn(),
  GetIdeaService: getIdeaService,
  GetIdeasService: getIdeasService,
  HideIdeaService: vi.fn(),
  MergeIdeaService: mergeIdeaService,
  UnvoteIdeaService: vi.fn(),
  UpdateIdeaStatusService: vi.fn(),
  VoteIdeaService: vi.fn(),
}));

const VIEWER_ID = 5;

vi.mock('@UserModule', () => ({
  useUserStore: () => ({ hasPermission: () => false, user: { id: VIEWER_ID } }),
}));

let routeId = '7';

// Partial: the real module stays underneath, because `@Helpers` builds a memory router on import.
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<Record<string, unknown>>()),
  useRoute: () => ({
    get params() {
      return { id: routeId };
    },
  }),
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: toastAdd }),
}));

import i18n from '@Libraries/i18n';
import IdeaView from './IdeaView.view.vue';

function makeComment(id: number, authorId: number) {
  return {
    id,
    ideaId: 7,
    body: 'Um comentário',
    isOfficial: false,
    hidden: false,
    deleted: false,
    createdAt: '2026-09-01T12:00:00Z',
    author: { id: authorId, name: 'Alguém' },
  };
}

const IDEA = {
  id: 7,
  title: 'Exportar em CSV',
  description: 'Descrição',
  type: 'feature',
  area: null,
  status: 'received',
  adminNote: null,
  votesCount: 0,
  commentsCount: 2,
  voted: false,
  canEdit: false,
  hidden: false,
  mergedIntoId: null,
  deliveredAt: null,
  createdAt: '2026-09-01T12:00:00Z',
  author: { id: 9, name: 'Marina' },
  attachment: null,
  comments: [makeComment(1, VIEWER_ID), makeComment(2, 9)],
};

const commentStub = {
  name: 'MIdeaComment',
  props: ['canDelete', 'comment'],
  template: '<div class="comment-stub" :data-can-delete="String(canDelete)" :data-id="comment.id" />',
};

function mountView() {
  return mountWithPlugins(IdeaView, {
    global: {
      stubs: {
        MIdeaComment: commentStub,
        MMainDialog: true,
        TheLayout: { template: '<div><slot name="pageHeader" /><slot /></div>' },
        ThePageHeader: { template: '<div><slot name="primaryAction" /><slot name="actions" /></div>' },
      },
    },
  });
}

beforeEach(() => {
  routeId = '7';
  getIdeaService.mockReset();
  getIdeaService.mockResolvedValue({ data: IDEA });
});

describe('IdeaView — ids', () => {
  it('asks for the idea by the numeric id in the route', async () => {
    mountView();
    await flushPromises();

    expect(getIdeaService).toHaveBeenCalledWith(7);
  });

  it('offers delete on the viewer’s own comment and on nobody else’s', async () => {
    const wrapper = mountView();
    await flushPromises();

    const comments = wrapper.findAll('.comment-stub');

    expect(comments.map((comment) => comment.attributes('data-can-delete'))).toEqual(['true', 'false']);
  });
});

describe('IdeaView — the maintainer answer', () => {
  /*
   * The label is text on `bg-panel-muted`, and the primary ramp does not flip: primary-500 there
   * measured 1.16:1 in the light scheme. A secondary label takes the muted role (celer-06).
   */
  it('labels the answer in a text role, never in a primary shade', async () => {
    getIdeaService.mockResolvedValue({ data: { ...IDEA, adminNote: 'Entra no próximo ciclo.' } });

    const wrapper = mountView();
    await flushPromises();

    const label = wrapper
      .findAll('span')
      .find((span) => span.text() === 'Answer from the person who maintains the product');

    expect(label).toBeDefined();
    expect(label?.classes()).toContain('text-muted');
    expect(label?.classes().some((name) => name.startsWith('text-primary-'))).toBe(false);
  });
});

describe('IdeaView — merging', () => {
  interface MergeVm {
    mergeOptions: Array<{ label: string; value: number }>;
    mergeTargetId: number | null;
    onMergeConfirm: () => void;
    onMergeOpen: () => void;
  }

  beforeEach(() => toastAdd.mockReset());

  // A hidden or already-merged target is refused by the API (422 targetUnavailable): do not offer it.
  it('offers neither this idea, nor a hidden one, nor one already merged, as the target', async () => {
    getIdeasService.mockResolvedValue({
      data: [
        { ...IDEA, id: 7, title: 'This one' },
        { ...IDEA, id: 8, title: 'Visible' },
        { ...IDEA, hidden: true, id: 9, title: 'Hidden' },
        { ...IDEA, id: 10, mergedIntoId: 8, title: 'Merged' },
      ],
    });

    const wrapper = mountView();
    await flushPromises();

    const VM = wrapper.vm as unknown as MergeVm;

    VM.onMergeOpen();
    await flushPromises();

    expect(VM.mergeOptions).toEqual([{ label: 'Visible', value: 8 }]);
  });

  // The API names the refusal; it is a warning in the person's language, not the red error.
  it('warns with the translated rule when the merge is refused', async () => {
    mergeIdeaService.mockRejectedValue({
      response: { status: 422, data: { errors: { targetId: ['ideas.merge.targetUnavailable'] } } },
      translatedMessage: i18n.global.t('ideas.merge.targetUnavailable'),
    });

    const wrapper = mountView();
    await flushPromises();

    const VM = wrapper.vm as unknown as MergeVm;

    VM.mergeTargetId = 8;
    VM.onMergeConfirm();
    await flushPromises();

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'warn',
        summary: 'This idea is hidden or already merged, so it cannot take the merge.',
      })
    );
  });

  it.each(['en', 'pt-BR'])('carries the three merge refusal keys in %s', (locale) => {
    ['ideas.merge.alreadyMerged', 'ideas.merge.sameIdea', 'ideas.merge.targetUnavailable'].forEach((key) =>
      expect(i18n.global.te(key, locale)).toBe(true)
    );
  });
});
