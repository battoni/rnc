import { computed, ref } from 'vue';
import type { Idea } from '../interfaces';
import { IDEA_STATUS_SEEN_STORAGE_KEY } from '../constants';

/**
 * "Something you supported moved."
 *
 * The reward for supporting an idea is finding out what happened to it, and the board is the only
 * place that can say so. This marks the ideas the viewer supports whose status has changed since
 * they last looked.
 *
 * DELIBERATELY QUIET. The brief asks for a product with no urgency, no alarm, no streak and no
 * guilt — so this is a dot on a card and one plain sentence, never a red badge, never a count in
 * the navigation, and never a notification. A person who does not care can ignore it forever and
 * the product will not nag.
 *
 * The memory is `localStorage`, not a table. It is a per-viewer convenience about what THIS person
 * has already looked at — it does not need to survive a new browser, it must never be shared, and
 * making the server carry it would mean a write on every page view. Every access is wrapped
 * because a private window, cleared site data, or a browser set to block storage all throw here,
 * and a marker failing must never take the board down with it.
 */
export default function useIdeaStatusWatch() {
  const seenStatuses = ref<Record<string, string>>(readSeen());

  // HELPERS

  function readSeen(): Record<string, string> {
    try {
      return JSON.parse(localStorage.getItem(IDEA_STATUS_SEEN_STORAGE_KEY) ?? '{}');
    } catch {
      return {};
    }
  }

  function writeSeen(next: Record<string, string>) {
    seenStatuses.value = next;

    try {
      localStorage.setItem(IDEA_STATUS_SEEN_STORAGE_KEY, JSON.stringify(next));
    } catch {
      // A viewer who cannot store anything simply never sees the marker. That is the whole cost.
    }
  }

  /**
   * Only ideas the viewer actually supports, and only once a status has been seen before. An idea
   * first met in a state that is not `received` is not "changed" — nobody was watching it yet.
   */
  function hasMoved(idea: Idea): boolean {
    if (!idea.voted) return false;

    const seen = seenStatuses.value[idea.id];

    return Boolean(seen) && seen !== idea.status;
  }

  // EVENTS

  /** Called when the board renders, so an idea is remembered from the first time it is seen. */
  function rememberAll(ideas: Idea[]) {
    const next = { ...seenStatuses.value };

    ideas.forEach((idea) => {
      if (idea.voted && !next[idea.id]) next[idea.id] = idea.status;
    });

    writeSeen(next);
  }

  /** Called when the person opens an idea — reading it is what acknowledges the change. */
  function acknowledge(idea: Idea) {
    writeSeen({ ...seenStatuses.value, [idea.id]: idea.status });
  }

  function movedCount(ideas: Idea[]): number {
    return ideas.filter(hasMoved).length;
  }

  return { acknowledge, hasMoved, movedCount, rememberAll, seenStatuses: computed(() => seenStatuses.value) };
}
