import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { IDEA_AREA } from '../enums';

const { routeName } = vi.hoisted(() => ({ routeName: { value: '' } }));

/*
 * A PARTIAL mock. Replacing the whole of vue-router breaks anything else in the graph that needs
 * `createRouter` / `createMemoryHistory` — the store pulls in the API provider, which pulls in the
 * real router — so only `useRoute` is swapped and the rest of the module stays real.
 */
vi.mock('vue-router', async (importActual) => {
  const actual = await importActual<typeof VueRouter>();

  return { ...actual, useRoute: () => ({ name: routeName.value }) };
});

const { default: useIdeaQuickCapture } = await import('./useIdeaQuickCapture');
const { default: useIdeaStore } = await import('../store');

beforeEach(() => {
  setActivePinia(createPinia());
});

describe('useIdeaQuickCapture', () => {
  /*
   * Routes carry a locale suffix (`tracks-pt-BR`, `tracks-en`) for the same screen, so the lookup
   * has to be on the part before it — otherwise the dialog only ever guesses right in one language.
   */
  it.each([
    ['home-pt-BR', IDEA_AREA.HOME],
    ['home-en', IDEA_AREA.HOME],
    ['personalization-en', IDEA_AREA.ACCOUNT],
    ['users-pt-BR', IDEA_AREA.ACCOUNT],
    ['roles-and-permissions-en', IDEA_AREA.ACCOUNT],
  ])('reads %s as the %s area', (name, expected) => {
    routeName.value = name;

    expect(useIdeaQuickCapture().areaForCurrentRoute()).toBe(expected);
  });

  /* A wrong-ish area is one click to fix; a blank required field is a reason not to send at all. */
  it('falls back to general on a screen nobody mapped', () => {
    routeName.value = 'some-unmapped-screen';

    expect(useIdeaQuickCapture().areaForCurrentRoute()).toBe(IDEA_AREA.GENERAL);
  });

  it('opens the dialog carrying the area of the screen the person was on', () => {
    routeName.value = 'users-pt-BR';

    const store = useIdeaStore();
    useIdeaQuickCapture().openCaptureForCurrentRoute();

    expect(store.isCaptureOpen).toBe(true);
    expect(store.captureArea).toBe(IDEA_AREA.ACCOUNT);
  });
});
