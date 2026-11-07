import { useRoute } from 'vue-router';
import { ROUTE_TO_IDEA_AREA } from '../constants';
import { IDEA_AREA } from '../enums';
import useIdeaStore from '../store';

/**
 * Opens the quick-capture dialog with the area already answered.
 *
 * The route name is the language-neutral half of the router: `tracks-pt-BR` and `tracks-en` are
 * the same screen, so the lookup is on the part before the locale suffix. An unmapped screen falls
 * back to GENERAL rather than to nothing — a wrong-ish area is recoverable in one click, and a
 * blank required field is a reason not to send the idea at all.
 */
export default function useIdeaQuickCapture() {
  const ideaStore = useIdeaStore();

  const route = useRoute();

  // HELPERS

  function areaForCurrentRoute(): IDEA_AREA {
    const ROUTE_NAME = String(route.name ?? '');
    const BASE_NAME = ROUTE_NAME.replace(/-(pt-BR|en)$/, '');

    return ROUTE_TO_IDEA_AREA[BASE_NAME] ?? IDEA_AREA.GENERAL;
  }

  // EVENTS

  function openCaptureForCurrentRoute() {
    ideaStore.openCapture(areaForCurrentRoute());
  }

  return { areaForCurrentRoute, openCaptureForCurrentRoute };
}
