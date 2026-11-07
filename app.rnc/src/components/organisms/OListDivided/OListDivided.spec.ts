import { Comment, defineComponent, Fragment, h } from 'vue';
import { describe, expect, it } from 'vitest';
import type { ComponentMountingOptions } from '@vue/test-utils';
import { AEmptyState } from '@/components/atoms/AEmptyState';
import { mountWithPlugins } from '@/test/mount';
import OListDivided from './OListDivided.vue';

type Slots = ComponentMountingOptions<typeof OListDivided>['slots'];

const primevueStubs = {
  Tag: { props: ['value', 'class', 'rounded', 'icon'], template: '<span class="tag-stub">{{ value }}</span>' },
  Button: {
    props: ['icon', 'severity', 'text', 'rounded', 'class', 'label', 'ariaLabel'],
    emits: ['click'],
    template:
      '<button class="button-stub" :data-icon="icon" :aria-label="ariaLabel" @click="$emit(\'click\', $event)">{{ label }}</button>',
  },
  Popover: defineComponent({
    emits: ['hide'],
    template: '<div class="popover-stub"><slot /></div>',
    methods: {
      hide() {
        this.$emit('hide');
      },
      toggle() {},
    },
  }),
  MMainDialog: {
    props: ['visible', 'isFooterless', 'title'],
    template:
      '<div v-if="visible" class="dialog-stub" :data-footerless="isFooterless"><slot /><slot name="footer" /></div>',
  },
};

const amountConfig = { valueKey: 'amount' };
const titleConfig = { valueKey: 'name' };

const items = [
  {
    id: '1',
    name: 'Alice',
    amount: '$100',
    status: 'active',
    statusTone: 'success',
    type: 'credit',
    typeTone: 'primary',
  },
  { id: '2', name: 'Bob', amount: '$200', status: 'inactive', statusTone: 'error', type: 'debit', typeTone: 'danger' },
];

function mount(props: Record<string, unknown> = {}, slots: Slots = {}) {
  return mountWithPlugins(OListDivided, {
    props: { items, amount: amountConfig, title: titleConfig, ...props },
    slots,
    global: { components: { AEmptyState }, stubs: primevueStubs },
  });
}

describe('OListDivided', () => {
  it('shows the empty state message when items is empty', () => {
    expect(mount({ items: [] }).text()).toContain('No items');
  });

  it('says whatever the caller names, through the shared empty state', () => {
    const wrapper = mount({ emptyMessage: 'common.error', items: [] });

    expect(wrapper.text()).toContain('An error occurred');
    expect(wrapper.text()).not.toContain('No items');
  });

  it('renders a row for each item', () => {
    const wrapper = mount();
    expect(wrapper.text()).toContain('Alice');
    expect(wrapper.text()).toContain('Bob');
  });

  it('renders the amount for each item', () => {
    const wrapper = mount();
    expect(wrapper.text()).toContain('$100');
    expect(wrapper.text()).toContain('$200');
  });

  it('renders without an amount config', () => {
    const wrapper = mountWithPlugins(OListDivided, {
      props: { items, title: titleConfig },
      global: { components: { AEmptyState }, stubs: primevueStubs },
    });
    expect(wrapper.text()).toContain('Alice');
    expect(wrapper.text()).not.toContain('$100');
  });

  it('applies valueFormatter to the amount', () => {
    const wrapper = mount({ amount: { valueKey: 'amount', valueFormatter: (raw: unknown) => `formatted-${raw}` } });
    expect(wrapper.text()).toContain('formatted-$100');
  });

  it('renders badge tags when badge config is provided', () => {
    const wrapper = mount({ badge: { valueKey: 'type', toneKey: 'typeTone' } });
    expect(wrapper.findAll('.tag-stub').length).toBeGreaterThanOrEqual(2);
  });

  it('renders status tags when status config is provided', () => {
    const wrapper = mount({ status: { valueKey: 'status', toneKey: 'statusTone' } });
    expect(wrapper.findAll('.tag-stub').some((tag) => tag.text() === 'active')).toBe(true);
  });

  it('renders a details button when showDetails is true', () => {
    const wrapper = mount({ showDetails: true });
    // One eye in the narrow controls and one in the wide strip; CSS container queries pick which shows.
    expect(wrapper.findAll('[data-icon="pi pi-eye"]').length).toBe(items.length * 2);
  });

  it('renders a ⋮ trigger per row only when the row has actions', () => {
    expect(mount({ canEdit: true }).findAll('[data-icon="pi pi-ellipsis-v"]').length).toBe(items.length);
    expect(mount({ showDetails: true }).find('[data-icon="pi pi-ellipsis-v"]').exists()).toBe(false);
  });

  it('names every icon-only control for a screen reader', () => {
    const wrapper = mount({ canEdit: true, canDelete: true, showDetails: true });

    const unnamed = wrapper
      .findAll('.button-stub')
      .filter((button) => !button.text() && !button.attributes('aria-label'));

    expect(unnamed).toHaveLength(0);
  });

  it('opens the ⋮ menu for that row and emits from it', async () => {
    const wrapper = mount({ canEdit: true });

    expect(wrapper.find('.popover-stub [data-icon="pi pi-pencil"]').exists()).toBe(false);

    await wrapper.findAll('[data-icon="pi pi-ellipsis-v"]')[1]!.trigger('click');

    await wrapper.find('.popover-stub [data-icon="pi pi-pencil"]').trigger('click');

    expect(wrapper.emitted('onEdit')?.[0]?.[0]).toMatchObject({ id: '2', name: 'Bob' });
    expect(wrapper.find('.popover-stub [data-icon="pi pi-pencil"]').exists()).toBe(false);
  });

  it('tells the #actions slot where it is rendered', async () => {
    const variants: string[] = [];
    const wrapper = mount(
      {},
      {
        actions: ({ variant }) => {
          variants.push(variant);

          return h('button', { class: `custom-action custom-action-${variant}` });
        },
      }
    );

    expect(wrapper.findAll('.custom-action-inline').length).toBe(items.length);

    await wrapper.find('[data-icon="pi pi-ellipsis-v"]').trigger('click');

    expect(wrapper.findAll('.popover-stub .custom-action-menu').length).toBe(1);
    expect(variants).toContain('menu');
  });

  it('renders the subtitle under the title', () => {
    const wrapper = mount({ subtitle: { valueKey: 'type' } });

    expect(wrapper.text()).toContain('credit');
  });

  it('renders only the gated inline action buttons', () => {
    const wrapper = mount({ canEdit: true });
    expect(wrapper.findAll('[data-icon="pi pi-pencil"]').length).toBe(items.length);
    expect(wrapper.find('[data-icon="pi pi-inbox"]').exists()).toBe(false);
    expect(wrapper.find('[data-icon="pi pi-trash"]').exists()).toBe(false);
  });

  it('emits onEdit with the item when the inline edit button is clicked', async () => {
    const wrapper = mount({ canEdit: true });

    await wrapper.find('[data-icon="pi pi-pencil"]').trigger('click');

    expect(wrapper.emitted('onEdit')?.[0]?.[0]).toMatchObject({ id: '1', name: 'Alice' });
  });

  it('emits onDelete with the item when the inline delete button is clicked', async () => {
    const wrapper = mount({ canDelete: true });

    await wrapper.find('[data-icon="pi pi-trash"]').trigger('click');

    expect(wrapper.emitted('onDelete')?.[0]?.[0]).toMatchObject({ id: '1', name: 'Alice' });
  });

  it('renders the custom #actions slot per row with the item', () => {
    const wrapper = mount({}, { actions: ({ item }) => h('button', { class: 'custom-action', 'data-id': item.id }) });
    expect(wrapper.findAll('.custom-action').length).toBe(items.length);
  });

  it('opens the detail dialog and passes the item to the #details slot', async () => {
    const wrapper = mount(
      { showDetails: true },
      { details: ({ item }) => h('span', { class: 'detail-content' }, String(item.name)) }
    );

    expect(wrapper.find('.dialog-stub').exists()).toBe(false);

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    expect(wrapper.find('.dialog-stub').exists()).toBe(true);
    expect(wrapper.find('.detail-content').text()).toBe('Alice');
  });

  it('mirrors the actions into the dialog footer and closes the dialog after acting', async () => {
    const wrapper = mount({ showDetails: true, canEdit: true });

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    expect(wrapper.findAll('.dialog-stub [data-icon="pi pi-pencil"]').length).toBe(1);

    await wrapper.find('.dialog-stub [data-icon="pi pi-pencil"]').trigger('click');

    expect(wrapper.emitted('onEdit')?.[0]?.[0]).toMatchObject({ id: '1' });
    expect(wrapper.find('.dialog-stub').exists()).toBe(false);
  });

  it('puts Edit after secondary actions in the detail footer', async () => {
    const wrapper = mount(
      { showDetails: true, canEdit: true, canDelete: true },
      { actions: () => h('button', { class: 'custom-action', 'data-icon': 'custom' }) }
    );

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    const footer = wrapper.get('.dialog-stub');
    const trash = footer.get('[data-icon="pi pi-trash"]').element;
    const custom = footer.get('.custom-action').element;
    const edit = footer.get('[data-icon="pi pi-pencil"]').element;

    expect(trash.compareDocumentPosition(edit) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(custom.compareDocumentPosition(edit) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  /*
   * A slot that renders nothing for THIS row must light nothing for it. The parent's `#actions`
   * gates each button on the item (archive hidden on the viewer's own row, delete only on an
   * inactive account), so the losing branch leaves a Comment inside a `<template>` Fragment —
   * and the ⋮ used to open on it, empty.
   */
  function actionsForFirstRowOnly(): Slots {
    return {
      actions: ({ item }) =>
        item.id === '1' ? h('button', { class: 'custom-action' }) : h(Fragment, null, [h(Comment, 'v-if')]),
    };
  }

  it('lights the ⋮ and the action strip only where the slot rendered something', () => {
    const wrapper = mount({}, actionsForFirstRowOnly());

    expect(wrapper.findAll('.custom-action')).toHaveLength(1);
    expect(wrapper.findAll('[data-icon="pi pi-ellipsis-v"]')).toHaveLength(1);
  });

  it('keeps the ⋮ on every row while a built-in action is on', () => {
    const wrapper = mount({ canEdit: true }, actionsForFirstRowOnly());

    expect(wrapper.findAll('[data-icon="pi pi-ellipsis-v"]')).toHaveLength(items.length);
  });

  // One row per case: every row draws two eyes (the narrow fold and the wide strip), so an index
  // into all of them is not a row.
  it('leaves the detail dialog footerless for a row with nothing to act on', async () => {
    const wrapper = mount({ items: [items[1]!], showDetails: true }, actionsForFirstRowOnly());

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    expect(wrapper.find('.dialog-stub').attributes('data-footerless')).toBe('true');
  });

  it('gives the detail dialog a footer for a row that does have one', async () => {
    const wrapper = mount({ items: [items[0]!], showDetails: true }, actionsForFirstRowOnly());

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');

    expect(wrapper.find('.dialog-stub').attributes('data-footerless')).toBe('false');
  });

  it('lets a custom #actions button dismiss the dialog via the slot close()', async () => {
    const wrapper = mount(
      { showDetails: true },
      { actions: ({ item, close }) => h('button', { class: 'custom-action', 'data-id': item.id, onClick: close }) }
    );

    await wrapper.find('[data-icon="pi pi-eye"]').trigger('click');
    expect(wrapper.find('.dialog-stub').exists()).toBe(true);

    await wrapper.find('.dialog-stub .custom-action').trigger('click');

    expect(wrapper.find('.dialog-stub').exists()).toBe(false);
  });
});
