import { nextTick, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';
import useIdeaStore from '../../../store';
import OIdeaCaptureDialog from './OIdeaCaptureDialog.vue';

const fullPath = ref('/ideias');

/*
 * Partial mock only: the store pulls the API provider, which pulls the real router. Replacing
 * the whole of vue-router breaks that graph; only `useRoute` is swapped.
 */
vi.mock('vue-router', async (importActual) => {
  const actual = await importActual<typeof VueRouter>();

  return {
    ...actual,
    useRoute: () => ({
      get fullPath() {
        return fullPath.value;
      },
    }),
  };
});

const STUBS = {
  MIdeaForm: { template: '<div class="idea-form-stub" />' },
  MMainDialog: {
    props: ['visible'],
    emits: ['update:visible', 'onClose'],
    template: '<div v-if="visible" class="dialog-stub"><slot /></div>',
  },
};

function mountDialog() {
  return mountWithPlugins(OIdeaCaptureDialog, { global: { stubs: STUBS } });
}

describe('OIdeaCaptureDialog', () => {
  beforeEach(() => {
    fullPath.value = '/ideias';
  });

  // Store after mount: mountWithPlugins installs a fresh Pinia; opening on a prior setActivePinia
  // would write a different instance than the one the dialog reads.

  // App.vue keys RouterView by fullPath, so navigation tears TheLayout down and mounts another.
  // The watch dies with the old instance; without clearing the store on unmount, the new dialog
  // opens again on top of the next screen.
  it('closes when its host is torn down (route-keyed remount)', async () => {
    const wrapper = mountDialog();
    const store = useIdeaStore();

    store.openCapture();
    await nextTick();

    expect(store.isCaptureOpen).toBe(true);

    wrapper.unmount();

    expect(store.isCaptureOpen).toBe(false);
  });

  it('closes when the route changes in place', async () => {
    const wrapper = mountDialog();
    const store = useIdeaStore();

    store.openCapture();
    await nextTick();

    expect(wrapper.find('.dialog-stub').exists()).toBe(true);

    fullPath.value = '/novidades';
    await nextTick();

    expect(store.isCaptureOpen).toBe(false);
    expect(wrapper.find('.dialog-stub').exists()).toBe(false);
  });

  it('stays open while the path is unchanged', async () => {
    mountDialog();
    const store = useIdeaStore();

    store.openCapture();
    await nextTick();

    fullPath.value = '/ideias';
    await nextTick();

    expect(store.isCaptureOpen).toBe(true);
  });
});
