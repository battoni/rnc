import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Idea } from '../interfaces';
import { IDEA_AREA, IDEA_STATUS, IDEA_TYPE } from '../enums';
import useIdeaStatusWatch from './useIdeaStatusWatch';

function makeIdea(overrides: Partial<Idea> = {}): Idea {
  return {
    id: 1,
    title: 'An idea',
    description: 'Descrição',
    type: IDEA_TYPE.IMPROVEMENT,
    area: IDEA_AREA.GENERAL,
    status: IDEA_STATUS.RECEIVED,
    adminNote: null,
    votesCount: 1,
    commentsCount: 0,
    voted: true,
    canEdit: false,
    hidden: false,
    mergedIntoId: null,
    deliveredAt: null,
    createdAt: null,
    author: null,
    attachment: null,
    ...overrides,
  };
}

beforeEach(() => {
  localStorage.clear();
});

describe('useIdeaStatusWatch', () => {
  it('says nothing about an idea seen for the first time', () => {
    const watcher = useIdeaStatusWatch();
    const IDEA = makeIdea();

    watcher.rememberAll([IDEA]);

    expect(watcher.hasMoved(IDEA)).toBe(false);
  });

  it('marks an idea whose status moved since it was last seen', () => {
    const first = useIdeaStatusWatch();
    first.rememberAll([makeIdea({ status: IDEA_STATUS.RECEIVED })]);

    const later = useIdeaStatusWatch();

    expect(later.hasMoved(makeIdea({ status: IDEA_STATUS.DELIVERED }))).toBe(true);
  });

  /* The marker is a reward for supporting something — it has nothing to say about the rest. */
  it('never marks an idea the viewer does not support', () => {
    const first = useIdeaStatusWatch();
    first.rememberAll([makeIdea({ status: IDEA_STATUS.RECEIVED })]);

    const later = useIdeaStatusWatch();

    expect(later.hasMoved(makeIdea({ status: IDEA_STATUS.DELIVERED, voted: false }))).toBe(false);
  });

  it('clears the mark once the person opens the idea', () => {
    const first = useIdeaStatusWatch();
    first.rememberAll([makeIdea({ status: IDEA_STATUS.RECEIVED })]);

    const later = useIdeaStatusWatch();
    const MOVED = makeIdea({ status: IDEA_STATUS.DELIVERED });
    expect(later.hasMoved(MOVED)).toBe(true);

    later.acknowledge(MOVED);

    expect(later.hasMoved(MOVED)).toBe(false);
  });

  it('counts only the ones that moved', () => {
    const first = useIdeaStatusWatch();
    first.rememberAll([
      makeIdea({ id: 1, status: IDEA_STATUS.RECEIVED }),
      makeIdea({ id: 2, status: IDEA_STATUS.RECEIVED }),
      makeIdea({ id: 3, status: IDEA_STATUS.RECEIVED }),
    ]);

    const later = useIdeaStatusWatch();
    const BOARD = [
      makeIdea({ id: 1, status: IDEA_STATUS.DELIVERED }),
      makeIdea({ id: 2, status: IDEA_STATUS.PLANNED }),
      makeIdea({ id: 3, status: IDEA_STATUS.RECEIVED }),
    ];

    expect(later.movedCount(BOARD)).toBe(2);
  });

  /*
   * A private window, cleared site data, or a browser set to block storage all throw here. A
   * marker failing must never take the board down with it.
   */
  it('survives a browser that refuses to store anything', () => {
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked');
    });

    const watcher = useIdeaStatusWatch();

    expect(() => watcher.rememberAll([makeIdea()])).not.toThrow();
    expect(watcher.hasMoved(makeIdea())).toBe(false);

    getItem.mockRestore();
    setItem.mockRestore();
  });

  it('ignores a corrupted stored value rather than crashing the board', () => {
    localStorage.setItem('app.rnc:ideas-seen-status', 'not json at all');

    expect(() => useIdeaStatusWatch()).not.toThrow();
    expect(useIdeaStatusWatch().hasMoved(makeIdea())).toBe(false);
  });
});
