// Register PrimeVue components explicitly — auto-import only works in Vite, not Vitest.
import Paginator from 'primevue/paginator';
import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import OPaginator from './OPaginator.vue';

const paginatorStub = {
  props: ['first', 'rows', 'totalRecords'],
  emits: ['page'],
  template: '<div class="paginator-stub" @click="$emit(\'page\', { page: 2 })" />',
};

function mountPaginator(props: { page: number; perPage: number; total: number }) {
  return mountWithPlugins(OPaginator, {
    props,
    global: { stubs: { Paginator: paginatorStub } },
  });
}

describe('OPaginator', () => {
  it('hides itself when everything fits on one page', () => {
    const wrapper = mountPaginator({ page: 1, perPage: 15, total: 12 });

    expect(wrapper.find('.paginator-stub').exists()).toBe(false);
  });

  it('renders and maps first offset when there are multiple pages', () => {
    const wrapper = mountPaginator({ page: 2, perPage: 15, total: 40 });

    const paginator = wrapper.findComponent(paginatorStub);
    expect(paginator.exists()).toBe(true);
    expect(paginator.props('first')).toBe(15);
    expect(paginator.props('totalRecords')).toBe(40);
  });

  it('emits a 1-based page from the 0-based Paginator event', async () => {
    const wrapper = mountPaginator({ page: 1, perPage: 15, total: 40 });

    await wrapper.find('.paginator-stub').trigger('click');

    expect(wrapper.emitted('onPage')?.[0]).toEqual([3]);
  });

  /*
   * PrimeVue's own report is `({currentPage} of {totalPages})`, in English, whatever the app
   * speaks. The template comes from the catalogue, with PrimeVue's placeholders passed through.
   */
  it('reports the current page in the app language', () => {
    const wrapper = mountWithPlugins(OPaginator, {
      props: { page: 2, perPage: 15, total: 40 },
      global: { components: { Paginator } },
    });

    expect(wrapper.find('.p-paginator-current').text()).toBe('Page 2 of 3');
  });

  /*
   * A thumb aims at "next" and lands on "last" when the two are 40px wide and 4px apart. The
   * growth is `pointer-coarse:` only — the mouse keeps the density — so what jsdom can check is
   * that the buttons and their row ask for it; e2e/theme/touch-chrome.spec.ts measures it.
   */
  it('asks for 44px buttons 12px apart under a finger', () => {
    const wrapper = mountWithPlugins(OPaginator, {
      props: { page: 2, perPage: 15, total: 40 },
      global: { components: { Paginator } },
    });

    expect(wrapper.find('.p-paginator-content').classes()).toContain('pointer-coarse:gap-3');

    for (const section of ['first', 'prev', 'next', 'last']) {
      expect(wrapper.find(`.p-paginator-${section}`).classes()).toEqual(
        expect.arrayContaining(['pointer-coarse:min-h-11', 'pointer-coarse:min-w-11'])
      );
    }
  });
});
