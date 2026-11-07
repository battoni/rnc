import { computed, onMounted } from 'vue';
import type { ComputedRef } from 'vue';
import { useIdeaStore } from '@IdeaModule';
import { useUserStore } from '@UserModule';

/**
 * Which navigation entries are currently wearing an attention dot, keyed by route name.
 *
 * This is the LOCAL extension point for nav badges, and it exists so that the navigation
 * components — which are synced from the rnc bootstrap — never have to import a feature
 * module. They ask this file; this file knows about the product.
 *
 * A dot here means "there is something here you have not seen", nothing else. Deliberately a
 * boolean and not a count: a number invites you to read it as a queue to clear, and Novidades is
 * something to read, not a backlog.
 */
function useNavBadges(): ComputedRef<Record<string, boolean>> {
  const ideaStore = useIdeaStore();
  const userStore = useUserStore();

  onMounted(onBadgesMount);

  return computed(() => ({ news: ideaStore.unreadNewsCount > 0 }));

  // EVENTS

  function onBadgesMount() {
    // Somebody who cannot open Novidades has nothing to be unread; asking would be a request per
    // page load to be told zero.
    if (!userStore.hasPermission('ideas.read')) return;

    ideaStore.loadUnreadNewsCount();
  }
}

export { useNavBadges };
