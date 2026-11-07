import { Comment, Fragment, h } from 'vue';
import { describe, expect, it } from 'vitest';
import type { ComponentMountingOptions } from '@vue/test-utils';
import { AEmptyState } from '@/components/atoms/AEmptyState';
import { mountWithPlugins } from '@/test/mount';
import OListFeed from './OListFeed.vue';

type Slots = ComponentMountingOptions<typeof OListFeed>['slots'];

const primevueStubs = {
  Tag: { props: ['value', 'class', 'rounded', 'icon'], template: '<span class="tag-stub">{{ value }}</span>' },
  Button: {
    props: ['icon', 'severity', 'text', 'rounded', 'class', 'label'],
    emits: ['click'],
    template: '<button class="button-stub" :data-icon="icon" @click="$emit(\'click\', $event)">{{ label }}</button>',
  },
  MMainDialog: {
    props: ['visible', 'isFooterless', 'title'],
    template:
      '<div v-if="visible" class="dialog-stub" :data-footerless="isFooterless"><slot /><slot name="footer" /></div>',
  },
};

const titleConfig = { valueKey: 'name' };

const items = [
  { id: '1', name: 'Alice', amount: '$100', type: 'credit', typeTone: 'success' },
  { id: '2', name: 'Bob', amount: '$200', type: 'debit', typeTone: 'danger' },
];

function mount(props: Record<string, unknown> = {}, slots: Slots = {}) {
  return mountWithPlugins(OListFeed, {
    props: { items, amount: { valueKey: 'amount' }, title: titleConfig, ...props },
    slots,
    global: { components: { AEmptyState }, stubs: primevueStubs },
  });
}

describe('OListFeed', () => {
  it('shows the empty state when items is empty', () => {
    expect(mount({ items: [] }).text()).toContain('No items');
  });

  it('says whatever the caller names, through the shared empty state', () => {
    const wrapper = mount({ emptyMessage: 'common.error', items: [] });

    expect(wrapper.text()).toContain('An error occurred');
    expect(wrapper.text()).not.toContain('No items');
  });

  it('renders a row per item with the amount', () => {
    const wrapper = mount();
    expect(wrapper.text()).toContain('Alice');
    expect(wrapper.text()).toContain('$100');
  });

  it('renders without an amount config (read-only timeline)', () => {
    const wrapper = mountWithPlugins(OListFeed, {
      props: { items, title: titleConfig },
      global: { components: { AEmptyState }, stubs: primevueStubs },
    });
    expect(wrapper.text()).toContain('Alice');
    expect(wrapper.text()).not.toContain('$100');
  });

  it('applies valueFormatter to the amount', () => {
    const wrapper = mount({ amount: { valueKey: 'amount', valueFormatter: (raw: unknown) => `fmt-${raw}` } });
    expect(wrapper.text()).toContain('fmt-$100');
  });

  it('renders only gated inline actions and emits with the item', async () => {
    const wrapper = mount({ canEdit: true });
    expect(wrapper.find('[data-icon="pi pi-trash"]').exists()).toBe(false);

    await wrapper.find('[data-icon="pi pi-pencil"]').trigger('click');

    expect(wrapper.emitted('onEdit')?.[0]?.[0]).toMatchObject({ id: '1', name: 'Alice' });
  });

  it('renders the custom #actions slot per row', () => {
    const wrapper = mount({}, { actions: ({ item }) => h('button', { class: 'custom-action', 'data-id': item.id }) });
    expect(wrapper.findAll('.custom-action').length).toBe(items.length);
  });

  it('opens the dialog with the item, mirrors actions, and closes after acting', async () => {
    const wrapper = mount(
      { showDetails: true, canDelete: true },
      { details: ({ item }) => h('span', { class: 'detail-content' }, String(item.name)) }
    );

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');
    expect(wrapper.find('.detail-content').text()).toBe('Alice');

    await wrapper.find('.dialog-stub [data-icon="pi pi-trash"]').trigger('click');

    expect(wrapper.emitted('onDelete')?.[0]?.[0]).toMatchObject({ id: '1' });
    expect(wrapper.find('.dialog-stub').exists()).toBe(false);
  });

  /*
   * A slot that draws nothing for THIS row must not give its detail dialog a footer. The organism
   * read `!!slots.actions`, which is true even when every button in the slot failed its own
   * `v-if` (the archive hidden on the viewer's own row, the delete only on an inactive account) —
   * an empty footer under the details.
   */
  it('leaves the detail dialog footerless when the slot draws nothing for that item', async () => {
    const wrapper = mount(
      { items: [items[0]!], showDetails: true },
      { actions: () => h(Fragment, null, [h(Comment, 'v-if')]) }
    );

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    expect(wrapper.find('.dialog-stub').attributes('data-footerless')).toBe('true');
  });

  it('gives it a footer when the slot does draw something', async () => {
    const wrapper = mount(
      { items: [items[0]!], showDetails: true },
      { actions: () => h('button', { class: 'custom-action' }) }
    );

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    expect(wrapper.find('.dialog-stub').attributes('data-footerless')).toBe('false');
  });
});
