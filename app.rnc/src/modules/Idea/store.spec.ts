import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Idea, NewsEntry } from './interfaces';
import type * as Services from './services';
import { IDEA_AREA, IDEA_SORT, IDEA_STATUS, IDEA_TYPE } from './enums';

const { services } = vi.hoisted(() => ({
  services: {
    getIdeas: vi.fn(),
    getNews: vi.fn(),
    getUnreadNewsCount: vi.fn(),
  },
}));

vi.mock('./services', async (importActual) => {
  const actual = await importActual<typeof Services>();

  return {
    ...actual,
    GetIdeasService: services.getIdeas,
    GetNewsService: services.getNews,
    GetUnreadNewsCountService: services.getUnreadNewsCount,
  };
});

const { default: useIdeaStore } = await import('./store');

function makeIdea(overrides: Partial<Idea> = {}): Idea {
  return {
    id: 1,
    title: 'Duplicate a block by dragging with Alt',
    description: 'Almost every block I place is the same as one already there.',
    type: IDEA_TYPE.IMPROVEMENT,
    area: IDEA_AREA.HOME,
    status: IDEA_STATUS.RECEIVED,
    adminNote: null,
    votesCount: 0,
    commentsCount: 0,
    voted: false,
    canEdit: true,
    hidden: false,
    mergedIntoId: null,
    deliveredAt: null,
    createdAt: '2026-09-01T10:00:00+00:00',
    author: { id: 9, name: 'Marina' },
    attachment: null,
    ...overrides,
  };
}

function makeNews(overrides: Partial<NewsEntry> = {}): NewsEntry {
  return {
    id: 11,
    title: 'The report now exports CSV',
    body: 'The button is at the top of the report.',
    subtitle: null,
    link: null,
    areas: [],
    published: true,
    publishedAt: '2026-08-25T10:00:00+00:00',
    createdAt: '2026-08-25T10:00:00+00:00',
    cover: null,
    idea: null,
    ...overrides,
  };
}

beforeEach(() => {
  setActivePinia(createPinia());
  vi.clearAllMocks();
});

describe('loading the board', () => {
  it('fills the list from the response', async () => {
    services.getIdeas.mockResolvedValue({ data: [makeIdea()] });

    const store = useIdeaStore();
    await store.loadIdeas();

    expect(store.ideas).toHaveLength(1);
    expect(store.hasIdeas).toBe(true);
    expect(store.isLoading).toBe(false);
  });

  it('empties the list rather than leaving stale rows when the request fails', async () => {
    services.getIdeas.mockResolvedValueOnce({ data: [makeIdea()] });

    const store = useIdeaStore();
    await store.loadIdeas();

    services.getIdeas.mockRejectedValueOnce(new Error('offline'));
    await store.loadIdeas();

    expect(store.ideas).toEqual([]);
    expect(store.isLoading).toBe(false);
  });

  it('shares one in-flight request between simultaneous callers', async () => {
    services.getIdeas.mockResolvedValue({ data: [makeIdea()] });

    const store = useIdeaStore();
    await Promise.all([store.ensureIdeasLoaded(), store.ensureIdeasLoaded(), store.ensureIdeasLoaded()]);

    expect(services.getIdeas).toHaveBeenCalledTimes(1);
  });

  it('sends the current filters', async () => {
    services.getIdeas.mockResolvedValue({ data: [] });

    const store = useIdeaStore();
    store.setFilters({ status: IDEA_STATUS.PLANNED, mine: true });
    await store.loadIdeas();

    expect(services.getIdeas).toHaveBeenCalledWith(
      expect.objectContaining({ status: IDEA_STATUS.PLANNED, mine: true })
    );
  });

  it('puts every filter back, sort included', () => {
    const store = useIdeaStore();
    store.setFilters({ status: IDEA_STATUS.DELIVERED, search: 'csv', sort: IDEA_SORT.RECENT });
    store.resetFilters();

    expect(store.filters.status).toBeNull();
    expect(store.filters.search).toBe('');
    expect(store.filters.sort).toBe(IDEA_SORT.VOTES);
  });
});

describe('racing requests', () => {
  /*
   * Filters change faster than the network answers. Type then clear, and the first response can
   * land after the second — leaving the board showing results for a query nobody is looking at.
   */
  it('ignores a slow response that lost the race to a newer one', async () => {
    let resolveSlow: (value: { data: Idea[] }) => void = () => {};
    const SLOW = new Promise<{ data: Idea[] }>((resolve) => (resolveSlow = resolve));

    services.getIdeas.mockReturnValueOnce(SLOW);
    services.getIdeas.mockResolvedValueOnce({ data: [makeIdea({ id: 100 })] });

    const store = useIdeaStore();
    const first = store.loadIdeas();
    const second = store.loadIdeas();

    await second;
    resolveSlow({ data: [makeIdea({ id: 101 })] });
    await first;

    expect(store.ideas.map((idea) => idea.id)).toEqual([100]);
  });

  it('does not let a stale failure empty a list a newer request filled', async () => {
    let rejectSlow: (reason: Error) => void = () => {};
    const SLOW = new Promise<{ data: Idea[] }>((_resolve, reject) => (rejectSlow = reject));

    services.getIdeas.mockReturnValueOnce(SLOW);
    services.getIdeas.mockResolvedValueOnce({ data: [makeIdea({ id: 100 })] });

    const store = useIdeaStore();
    const first = store.loadIdeas();
    await store.loadIdeas();

    rejectSlow(new Error('lenta'));
    await first;

    expect(store.ideas.map((idea) => idea.id)).toEqual([100]);
    expect(store.isLoading).toBe(false);
  });
});

describe('applyIdeaSaved', () => {
  it('prepends an idea the list has never seen', async () => {
    services.getIdeas.mockResolvedValue({ data: [makeIdea({ id: 1 })] });

    const store = useIdeaStore();
    await store.loadIdeas();
    store.applyIdeaSaved(makeIdea({ id: 2, title: 'Nova' }));

    expect(store.ideas.map((idea) => idea.id)).toEqual([2, 1]);
  });

  it('replaces in place, keeping the position', async () => {
    services.getIdeas.mockResolvedValue({
      data: [makeIdea({ id: 1 }), makeIdea({ id: 2 }), makeIdea({ id: 3 })],
    });

    const store = useIdeaStore();
    await store.loadIdeas();
    store.applyIdeaSaved(makeIdea({ id: 2, votesCount: 9, voted: true }));

    expect(store.ideas.map((idea) => idea.id)).toEqual([1, 2, 3]);
    expect(store.ideas[1]?.votesCount).toBe(9);
    expect(store.ideas[1]?.voted).toBe(true);
  });

  /*
   * The trap this guards: a vote response carries the row but not always every relation the
   * board query eager-loads. A straight replacement blanked the author on the card until the
   * next full read, so the incoming row is merged OVER the stored one.
   */
  it('merges rather than replaces, so a partial response cannot blank the card', async () => {
    services.getIdeas.mockResolvedValue({ data: [makeIdea({ id: 1 })] });

    const store = useIdeaStore();
    await store.loadIdeas();

    const PARTIAL = { ...makeIdea({ id: 1, votesCount: 3 }) } as Idea;
    delete (PARTIAL as Partial<Idea>).author;
    store.applyIdeaSaved(PARTIAL);

    expect(store.ideas[0]?.votesCount).toBe(3);
    expect(store.ideas[0]?.author?.name).toBe('Marina');
  });

  it('drops a withdrawn idea', async () => {
    services.getIdeas.mockResolvedValue({ data: [makeIdea({ id: 1 }), makeIdea({ id: 2 })] });

    const store = useIdeaStore();
    await store.loadIdeas();
    store.applyIdeaRemoved(1);

    expect(store.ideas.map((idea) => idea.id)).toEqual([2]);
  });
});

describe('the quick-capture dialog', () => {
  it('opens carrying the area it was given and closes clearing it', () => {
    const store = useIdeaStore();

    store.openCapture(IDEA_AREA.ACCOUNT);
    expect(store.isCaptureOpen).toBe(true);
    expect(store.captureArea).toBe(IDEA_AREA.ACCOUNT);

    store.closeCapture();
    expect(store.isCaptureOpen).toBe(false);
    expect(store.captureArea).toBeNull();
  });

  it('opens with no area when it was opened from the board itself', () => {
    const store = useIdeaStore();
    store.openCapture(null);

    expect(store.isCaptureOpen).toBe(true);
    expect(store.captureArea).toBeNull();
  });
});

describe('news', () => {
  it('loads the changelog', async () => {
    services.getNews.mockResolvedValue({ data: [makeNews(), makeNews({ id: 12, published: false })] });

    const store = useIdeaStore();
    await store.loadNews();

    expect(store.newsEntries).toHaveLength(2);
  });

  it('empties the list rather than keeping stale entries when the load fails', async () => {
    services.getNews.mockResolvedValue({ data: [makeNews()] });

    const store = useIdeaStore();
    await store.loadNews();

    services.getNews.mockRejectedValue(new Error('offline'));
    await store.loadNews();

    expect(store.newsEntries).toEqual([]);
  });

  /*
   * The three granular mutations. The API answers every write with the saved entry, so the list is
   * patched in place — a refetch would throw away the scroll position to move one card.
   */
  it('puts a new entry at the top, where the newest belongs', () => {
    const store = useIdeaStore();
    store.newsEntries = [makeNews({ id: 11 })];

    store.addNewsEntry(makeNews({ id: 12 }));

    expect(store.newsEntries.map((entry) => entry.id)).toEqual([12, 11]);
  });

  it('replaces one entry by id and leaves the rest alone', () => {
    const store = useIdeaStore();
    store.newsEntries = [makeNews({ id: 11, title: 'Antigo' }), makeNews({ id: 12 })];

    store.replaceNewsEntry(makeNews({ id: 11, title: 'Novo' }));

    expect(store.newsEntries[0]?.title).toBe('Novo');
    expect(store.newsEntries).toHaveLength(2);
  });

  it('removes one entry by id', () => {
    const store = useIdeaStore();
    store.newsEntries = [makeNews({ id: 11 }), makeNews({ id: 12 })];

    store.removeNewsEntry(11);

    expect(store.newsEntries.map((entry) => entry.id)).toEqual([12]);
  });

  it('reads the unread count for the navigation dot', async () => {
    services.getUnreadNewsCount.mockResolvedValue({ data: { unread: 3 } });

    const store = useIdeaStore();
    await store.loadUnreadNewsCount();

    expect(store.unreadNewsCount).toBe(3);
  });

  /* A dot that lights up because the request failed is worse than a dot that stays dark. */
  it('falls back to no dot when the count cannot be fetched', async () => {
    services.getUnreadNewsCount.mockRejectedValue(new Error('offline'));

    const store = useIdeaStore();
    await store.loadUnreadNewsCount();

    expect(store.unreadNewsCount).toBe(0);
  });

  it('takes one off the dot when an unread entry is opened', async () => {
    services.getUnreadNewsCount.mockResolvedValue({ data: { unread: 2 } });

    const store = useIdeaStore();
    await store.loadUnreadNewsCount();

    store.noteNewsRead(makeNews({ read: false }));

    expect(store.unreadNewsCount).toBe(1);
  });

  /* Re-opening something already read must not keep draining a count that is already right. */
  it('leaves the dot alone for an entry that was already read', async () => {
    services.getUnreadNewsCount.mockResolvedValue({ data: { unread: 2 } });

    const store = useIdeaStore();
    await store.loadUnreadNewsCount();

    store.noteNewsRead(makeNews({ read: true }));

    expect(store.unreadNewsCount).toBe(2);
  });

  it('never takes the count below zero', () => {
    const store = useIdeaStore();

    store.noteNewsRead(makeNews({ read: false }));

    expect(store.unreadNewsCount).toBe(0);
  });
});

describe('$reset', () => {
  it('wipes lists, flags and capture state so logout does not leak the previous session', async () => {
    services.getIdeas.mockResolvedValue({ data: [makeIdea()] });
    services.getNews.mockResolvedValue({ data: [makeNews()] });

    const store = useIdeaStore();
    await store.loadIdeas();
    await store.loadNews();
    store.openCapture(IDEA_AREA.HOME);
    store.hasIdeasLoadError = true;
    store.hasNewsLoadError = true;
    store.unreadNewsCount = 4;

    store.$reset();

    expect(store.ideas).toEqual([]);
    expect(store.newsEntries).toEqual([]);
    expect(store.isLoading).toBe(false);
    expect(store.isNewsLoading).toBe(false);
    expect(store.hasIdeasLoadError).toBe(false);
    expect(store.hasNewsLoadError).toBe(false);
    expect(store.unreadNewsCount).toBe(0);
    expect(store.isCaptureOpen).toBe(false);
    expect(store.captureArea).toBeNull();
  });
});
