import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import { describe, expect, it, vi } from 'vitest';
import type * as TipModule from '@TipModule';
import { mountWithPlugins } from '@/test/mount';
import { useTipStore } from '@Stores';
import ATip from './ATip.vue';

vi.mock('@TipModule', async (importOriginal) => ({
  ...(await importOriginal<typeof TipModule>()),
  GetActiveTipsService: vi.fn(() => Promise.resolve({ data: [] })),
}));

/*
 * Kept apart from ATip.spec.ts: these are the two review fixes (tip-002, tip-014) that reached the
 * atom from the Tip module's side.
 */
function mountTip(content: string) {
  const pinia = createPinia();
  setActivePinia(pinia);

  const tipStore = useTipStore();
  tipStore.isLoaded = true;
  tipStore.contents = { 'users.list': { anchor_key: 'users.list', content, id: 7 } };

  return mountWithPlugins(ATip, {
    props: { tipKey: 'users.list' },
    global: { components: { Button }, plugins: [pinia], stubs: { Popover: { template: '<div><slot /></div>' } } },
  });
}

describe('ATip — links and touch', () => {
  // A link that navigated in the same tab took the person off the screen the help belongs to.
  it('opens a link in the tip in a new tab, with the rel that makes it safe', () => {
    const wrapper = mountTip('<p>See <a href="https://example.test">the guide</a>.</p>');

    const LINK = wrapper.get('[data-testid="tip-body"] a');

    expect(LINK.attributes('target')).toBe('_blank');
    expect(LINK.attributes('rel')).toBe('noopener noreferrer');
  });

  // Drawn at 32px for density; on a touch screen the box itself grows to 44px. `touch-target` is
  // not an option on an icon-only PrimeVue button (celer-06).
  it('grows the trigger to 44px on a coarse pointer', () => {
    const wrapper = mountTip('<p>A tip.</p>');

    expect(wrapper.get('button').classes()).toContain('pointer-coarse:size-11');
    expect(wrapper.get('button').classes()).not.toContain('touch-target');
  });
});
