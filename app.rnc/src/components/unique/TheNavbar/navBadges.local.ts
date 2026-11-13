import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Which navigation entries are currently wearing an attention dot, keyed by route name.
 *
 * This is the LOCAL extension point for nav badges, and it exists so that the navigation
 * components — which are synced from the rnc bootstrap — never have to import a feature
 * module. They ask this file; this file knows about the product.
 *
 * A dot here means "there is something here you have not seen", nothing else. Empty in F1 of
 * rnc-1: the bell of F4 counts unread notifications on its own, in the page header.
 */
function useNavBadges(): ComputedRef<Record<string, boolean>> {
  return computed(() => ({}));
}

export { useNavBadges };
