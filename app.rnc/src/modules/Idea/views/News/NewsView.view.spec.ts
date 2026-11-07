import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

/*
 * The two behaviours covered here are the ones the view owns rather than delegates, and both were
 * shipped without a spec of their own:
 *
 * 1. `openSharedEntry` — a `?entrada=` link that names nothing gets a toast and loses the query.
 *    `loadNews` swallows its own failure and RESOLVES, so without a guard a network blip reaches
 *    the same code with an empty list, calls a perfectly good link missing, and then deletes it.
 * 2. `startReactionsCooldown` — a 429 pauses applause for a moment instead of opening one toast
 *    per click, and the timer is dropped on unmount.
 */

const {
  createNewsService,
  deleteNewsService,
  getIdeasService,
  getNewsReadersService,
  getNewsService,
  markNewsReadService,
  publishNewsService,
  reactToNewsService,
  updateNewsService,
} = vi.hoisted(() => ({
  createNewsService: vi.fn(),
  deleteNewsService: vi.fn(),
  getIdeasService: vi.fn(),
  getNewsReadersService: vi.fn(),
  getNewsService: vi.fn(),
  markNewsReadService: vi.fn(),
  publishNewsService: vi.fn(),
  reactToNewsService: vi.fn(),
  updateNewsService: vi.fn(),
}));

vi.mock('../../services', () => ({
  CreateNewsService: createNewsService,
  DeleteNewsService: deleteNewsService,
  GetIdeasService: getIdeasService,
  GetNewsReadersService: getNewsReadersService,
  GetNewsService: getNewsService,
  MarkNewsReadService: markNewsReadService,
  PublishNewsService: publishNewsService,
  ReactToNewsService: reactToNewsService,
  UpdateNewsService: updateNewsService,
}));

vi.mock('@UserModule', () => ({
  useUserStore: () => ({ hasPermission: () => false }),
}));

const replace = vi.fn();
let routeQuery: Record<string, string> = {};

// Partial: the real module stays underneath, because `@Helpers` builds a memory router on import.
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<Record<string, unknown>>()),
  useRoute: () => ({
    get query() {
      return routeQuery;
    },
  }),
  useRouter: () => ({ replace }),
}));

const toastAdd = vi.fn();

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: toastAdd }),
}));

import NewsView from './NewsView.view.vue';

const ENTRY = {
  id: 21,
  title: 'Uma entrada',
  standfirst: 'Resumo',
  body: '<p>corpo</p>',
  published: true,
  published_at: '2026-09-01T12:00:00Z',
  created_at: '2026-09-01T12:00:00Z',
  cover: null,
  reactions: {},
  myReactions: {},
  readCount: 0,
};

const newsCardStub = {
  name: 'MNewsCard',
  props: ['canManage', 'entry', 'isPublishing', 'reactionsDisabled'],
  emits: ['onDelete', 'onEdit', 'onOpen', 'onPublish', 'onReact', 'onReaders'],
  template: '<div class="news-card-stub" />',
};

function mountView() {
  return mountWithPlugins(NewsView, {
    global: {
      stubs: {
        MNewsCard: newsCardStub,
        MNewsArticle: true,
        MNewsForm: true,
        MNewsReadersList: true,
        MMainDialog: true,
        TheLayout: { template: '<div><slot name="pageHeader" /><slot /></div>' },
        ThePageHeader: { template: '<div><slot name="primaryAction" /><slot name="actions" /></div>' },
        AEmptyState: true,
        AErrorState: true,
      },
    },
  });
}

beforeEach(() => {
  vi.useFakeTimers();
  routeQuery = {};
  replace.mockClear();
  toastAdd.mockClear();
  getNewsService.mockResolvedValue({ data: [ENTRY] });
  getIdeasService.mockResolvedValue({ data: [] });
  markNewsReadService.mockResolvedValue({ data: ENTRY });
});

afterEach(() => {
  vi.useRealTimers();
});

describe('NewsView — a shared ?entrada= link', () => {
  it('says nothing and keeps the query when the list itself failed to load', async () => {
    // The screen already shows "could not load" with a Retry. A second, wronger sentence on top of
    // it helps nobody — and stripping the query would leave Retry with nothing left to open.
    getNewsService.mockRejectedValue({ response: { status: 500 } });
    routeQuery = { entrada: '21' };

    mountView();
    await flushPromises();

    expect(toastAdd).not.toHaveBeenCalled();
    expect(replace).not.toHaveBeenCalled();
  });

  it('reports a genuinely missing entry and strips the query', async () => {
    routeQuery = { entrada: 'gone-long-ago' };

    mountView();
    await flushPromises();

    expect(toastAdd).toHaveBeenCalledOnce();
    expect(replace).toHaveBeenCalledWith({ query: {} });
  });

  it('leaves a query that names a real entry alone', async () => {
    routeQuery = { entrada: '21' };

    mountView();
    await flushPromises();

    expect(toastAdd).not.toHaveBeenCalled();
    expect(replace).not.toHaveBeenCalled();
  });

  it('does nothing at all when no entry was shared', async () => {
    mountView();
    await flushPromises();

    expect(toastAdd).not.toHaveBeenCalled();
    expect(replace).not.toHaveBeenCalled();
  });
});

describe('NewsView — applause after a 429', () => {
  it('cools the buttons down, then lets them go again', async () => {
    reactToNewsService.mockRejectedValue({ response: { status: 429 } });

    const wrapper = mountView();
    await flushPromises();

    const card = wrapper.findComponent(newsCardStub);
    expect(card.props('reactionsDisabled')).toBe(false);

    card.vm.$emit('onReact', 'clap', () => {});
    await flushPromises();

    expect(card.props('reactionsDisabled')).toBe(true);

    vi.advanceTimersByTime(3000);
    await flushPromises();

    expect(card.props('reactionsDisabled')).toBe(false);
  });

  it('does not cool down on an ordinary failure', async () => {
    reactToNewsService.mockRejectedValue({ response: { status: 500 } });

    const wrapper = mountView();
    await flushPromises();

    const card = wrapper.findComponent(newsCardStub);

    card.vm.$emit('onReact', 'clap', () => {});
    await flushPromises();

    expect(card.props('reactionsDisabled')).toBe(false);
  });

  it('drops the cooldown timer when the view goes away', async () => {
    reactToNewsService.mockRejectedValue({ response: { status: 429 } });

    const wrapper = mountView();
    await flushPromises();

    wrapper.findComponent(newsCardStub).vm.$emit('onReact', 'clap', () => {});
    await flushPromises();

    wrapper.unmount();

    // A timer still holding a reference to an unmounted view's ref is the leak this guards.
    expect(vi.getTimerCount()).toBe(0);
  });
});

describe('NewsView — a load that failed', () => {
  it('shows only the failure, never the filter that "emptied the page"', async () => {
    // A failed load and an empty filter are different facts (celer-07). With the list emptied by the
    // failure, the filter block used to render under "could not load" and claim the filter did it.
    getNewsService.mockRejectedValue({ response: { status: 500 } });

    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.find('[data-testid="news-load-error"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="news-empty-filter"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="news-empty"]').exists()).toBe(false);
  });
});
