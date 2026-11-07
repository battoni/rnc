import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

/*
 * A load that failed is not an empty board (celer-07). The store records the failure in
 * `hasIdeasLoadError` and empties the list, so a view that only looks at the list tells the person
 * "nothing here yet" — and offers to write the first idea — while the API is down.
 */

const { getIdeasService } = vi.hoisted(() => ({ getIdeasService: vi.fn() }));

vi.mock('../../services', () => ({
  GetIdeasService: getIdeasService,
  GetNewsService: vi.fn(),
  GetUnreadNewsCountService: vi.fn(),
  UnvoteIdeaService: vi.fn(),
  UpdateIdeaService: vi.fn(),
  VoteIdeaService: vi.fn(),
}));

vi.mock('@UserModule', () => ({
  useUserStore: () => ({ hasPermission: () => true }),
}));

// Partial: the real module stays underneath, because `@Helpers` builds a memory router on import.
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<Record<string, unknown>>()),
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() }),
}));

import IdeasView from './IdeasView.view.vue';

const IDEA = {
  id: 7,
  title: 'Exportar em CSV',
  description: 'Descrição',
  type: 'feature',
  area: null,
  status: 'received',
  adminNote: null,
  votesCount: 0,
  commentsCount: 0,
  voted: false,
  canEdit: false,
  hidden: false,
  mergedIntoId: null,
  deliveredAt: null,
  createdAt: '2026-09-01T12:00:00Z',
  author: { id: 9, name: 'Marina' },
  attachment: null,
};

function mountView() {
  return mountWithPlugins(IdeasView, {
    global: {
      stubs: {
        MIdeaCard: { template: '<div class="idea-card-stub" />' },
        MIdeaFilters: true,
        MIdeaForm: true,
        MMainDialog: true,
        TheLayout: { template: '<div><slot name="pageHeader" /><slot /></div>' },
        ThePageHeader: { template: '<div><slot name="primaryAction" /><slot name="actions" /></div>' },
      },
    },
  });
}

beforeEach(() => {
  getIdeasService.mockReset();
});

describe('IdeasView — a load that failed', () => {
  it('says the board did not load and offers to retry, not "nothing here yet"', async () => {
    getIdeasService.mockRejectedValue({ response: { status: 500 } });

    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.find('[data-testid="idea-load-error"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="idea-empty"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="idea-empty-filtered"]').exists()).toBe(false);
  });

  it('asks again when Retry is pressed, and shows the board that arrives', async () => {
    getIdeasService.mockRejectedValueOnce({ response: { status: 500 } });
    getIdeasService.mockResolvedValueOnce({ data: [IDEA] });

    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="idea-load-error"] button').trigger('click');
    await flushPromises();

    expect(getIdeasService).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-testid="idea-load-error"]').exists()).toBe(false);
    expect(wrapper.findAll('.idea-card-stub')).toHaveLength(1);
  });

  it('still shows the empty board when the list arrived empty', async () => {
    getIdeasService.mockResolvedValue({ data: [] });

    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.find('[data-testid="idea-empty"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="idea-load-error"]').exists()).toBe(false);
  });
});
