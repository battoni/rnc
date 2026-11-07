import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import type { IDEA_AREA } from './enums';
import type { Idea, IdeaFilters, NewsEntry } from './interfaces';
import { DEFAULT_IDEA_FILTERS } from './constants';
import { GetIdeasService, GetNewsService, GetUnreadNewsCountService } from './services';

/**
 * The board's state, plus the two pieces of it that have to be reachable from anywhere: the
 * quick-capture dialog, which opens on top of any screen, and the ideas list itself, which the
 * dialog's success has to fold into.
 */
export default defineStore('idea', () => {
  const ideas = ref<Idea[]>([]);
  const newsEntries = ref<NewsEntry[]>([]);
  const filters = ref<IdeaFilters>({ ...DEFAULT_IDEA_FILTERS });
  const isLoading = ref(false);
  const isNewsLoading = ref(false);
  const unreadNewsCount = ref(0);
  /*
   * A load that FAILED renders identically to a board with nothing on it: both are an empty list.
   * "Nobody has written anything yet" and "we could not reach the server" call for opposite
   * responses from the person reading, so the screens have to be able to tell them apart.
   */
  const hasIdeasLoadError = ref(false);
  const hasNewsLoadError = ref(false);
  const isCaptureOpen = ref(false);
  const captureArea = ref<IDEA_AREA | null>(null);

  /*
   * The in-flight promise is shared so the board mounting, the dialog closing and a filter
   * landing in the same tick cost one request rather than three.
   */
  let inFlightRequest: Promise<void> | null = null;

  /*
   * Which request is allowed to write the list. Filters change faster than the network answers —
   * type, then clear, and the first response can land after the second, leaving the board showing
   * results for a query nobody is looking at. Only the newest request writes.
   */
  let latestRequestId = 0;

  const hasIdeas = computed(() => ideas.value.length > 0);

  // HELPERS

  /**
   * Replace by id, or prepend when it is new. Never a refetch: every mutating endpoint answers
   * with the affected row precisely so the list can be patched instead of reloaded.
   *
   * The incoming row is MERGED over the stored one rather than replacing it. A response built
   * from a write does not always carry every relation the board query eager-loads, and a straight
   * replacement would blank the author or the attachment on the card until the next full read.
   */
  function applyIdeaSaved(idea: Idea) {
    const INDEX = ideas.value.findIndex((item) => item.id === idea.id);

    if (INDEX === -1) {
      ideas.value = [idea, ...ideas.value];
      return;
    }

    ideas.value = ideas.value.map((item, index) => (index === INDEX ? { ...item, ...idea } : item));
  }

  function applyIdeaRemoved(id: number) {
    ideas.value = ideas.value.filter((item) => item.id !== id);
  }

  function setFilters(next: Partial<IdeaFilters>) {
    filters.value = { ...filters.value, ...next };
  }

  function resetFilters() {
    filters.value = { ...DEFAULT_IDEA_FILTERS };
  }

  function openCapture(area: IDEA_AREA | null = null) {
    captureArea.value = area;
    isCaptureOpen.value = true;
  }

  function closeCapture() {
    isCaptureOpen.value = false;
    captureArea.value = null;
  }

  // EVENTS

  function loadIdeas(): Promise<void> {
    const REQUEST_ID = ++latestRequestId;

    isLoading.value = true;

    return GetIdeasService(filters.value)
      .then(({ data }) => {
        if (REQUEST_ID !== latestRequestId) return;

        hasIdeasLoadError.value = false;
        ideas.value = data;
      })
      .catch(() => {
        if (REQUEST_ID !== latestRequestId) return;

        hasIdeasLoadError.value = true;
        ideas.value = [];
      })
      .finally(() => {
        if (REQUEST_ID === latestRequestId) isLoading.value = false;
      });
  }

  function ensureIdeasLoaded(): Promise<void> {
    if (inFlightRequest) return inFlightRequest;

    inFlightRequest = loadIdeas().finally(() => (inFlightRequest = null));

    return inFlightRequest;
  }

  /*
   * The three granular news mutations. The API answers every write with the saved entry, so the
   * list is patched in place — a refetch after each edit would throw away the scroll position and
   * re-render the whole changelog to move one card.
   */
  function addNewsEntry(entry: NewsEntry): void {
    newsEntries.value = [entry, ...newsEntries.value];
  }

  function replaceNewsEntry(entry: NewsEntry): void {
    newsEntries.value = newsEntries.value.map((item) => (item.id === entry.id ? entry : item));
  }

  function removeNewsEntry(id: number): void {
    newsEntries.value = newsEntries.value.filter((item) => item.id !== id);
  }

  /*
   * The nav dot. Fetched once per session and then kept current locally: marking an entry read
   * decrements it here rather than asking the server again, because the dot is on screen the whole
   * time and a round trip to learn what we just did is a round trip for nothing.
   */
  function loadUnreadNewsCount(): Promise<void> {
    return GetUnreadNewsCountService()
      .then(({ data }) => void (unreadNewsCount.value = data.unread))
      .catch(() => void (unreadNewsCount.value = 0));
  }

  function noteNewsRead(entry: NewsEntry): void {
    if (entry.read) return;

    unreadNewsCount.value = Math.max(0, unreadNewsCount.value - 1);
  }

  function loadNews(): Promise<void> {
    isNewsLoading.value = true;

    return GetNewsService()
      .then(({ data }) => {
        hasNewsLoadError.value = false;
        newsEntries.value = data;
      })
      .catch(() => {
        hasNewsLoadError.value = true;
        newsEntries.value = [];
      })
      .finally(() => (isNewsLoading.value = false));
  }

  /** Pinia setup stores do not get `$reset` for free — logout needs one. */
  function $reset(): void {
    ideas.value = [];
    newsEntries.value = [];
    filters.value = { ...DEFAULT_IDEA_FILTERS };
    isLoading.value = false;
    isNewsLoading.value = false;
    unreadNewsCount.value = 0;
    hasIdeasLoadError.value = false;
    hasNewsLoadError.value = false;
    isCaptureOpen.value = false;
    captureArea.value = null;
    inFlightRequest = null;
  }

  return {
    $reset,
    addNewsEntry,
    applyIdeaRemoved,
    applyIdeaSaved,
    captureArea,
    closeCapture,
    ensureIdeasLoaded,
    filters,
    hasIdeas,
    hasIdeasLoadError,
    hasNewsLoadError,
    ideas,
    isCaptureOpen,
    isLoading,
    isNewsLoading,
    loadIdeas,
    loadNews,
    loadUnreadNewsCount,
    newsEntries,
    noteNewsRead,
    openCapture,
    removeNewsEntry,
    replaceNewsEntry,
    resetFilters,
    setFilters,
    unreadNewsCount,
  };
});
