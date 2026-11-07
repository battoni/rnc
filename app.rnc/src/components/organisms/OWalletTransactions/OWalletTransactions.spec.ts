import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import OWalletTransactions from './OWalletTransactions.vue';

const dataTableStub = {
  name: 'DataTable',
  props: ['value', 'scrollHeight', 'dataKey', 'scrollable', 'stripedRows'],
  template: `
    <div class="datatable-stub">
      <slot name="header" />
      <template v-if="!value || value.length === 0">
        <slot name="empty" />
      </template>
    </div>
  `,
};

const primevueStubs = {
  DataTable: dataTableStub,
  Column: { template: '<div class="column-stub" />' },
  Tag: { props: ['value', 'class', 'rounded', 'icon'], template: '<span class="tag-stub">{{ value }}</span>' },
  Button: { props: ['label', 'link', 'class'], template: '<button class="button-stub">{{ label }}</button>' },
};

const transactions = [
  {
    id: '1',
    type: 'Deposit',
    typeTone: 'success' as const,
    entity: 'Alice',
    amount: '$500',
    date: '2026-01-01',
    status: 'Completed',
    statusTone: 'success' as const,
  },
  {
    id: '2',
    type: 'Withdrawal',
    typeTone: 'danger' as const,
    entity: 'Bob',
    amount: '$200',
    date: '2026-01-02',
    status: 'Pending',
    statusTone: 'warning' as const,
  },
];

function mount(props: Record<string, unknown> = {}) {
  return mountWithPlugins(OWalletTransactions, {
    props: { transactions, ...props },
    global: { stubs: primevueStubs },
  });
}

describe('OWalletTransactions', () => {
  it('renders the section title (i18n: "Latest transactions")', () => {
    expect(mount().text()).toContain('Latest transactions');
  });

  it('renders the "View all" link button', () => {
    expect(mount().find('.button-stub').exists()).toBe(true);
  });

  it('shows the empty state message when transactions is empty', () => {
    const wrapper = mountWithPlugins(OWalletTransactions, {
      props: { transactions: [] },
      global: { stubs: primevueStubs },
    });
    expect(wrapper.text()).toContain('No transactions');
  });

  it('passes the transactions array to DataTable', () => {
    const wrapper = mount();
    expect(wrapper.findComponent(dataTableStub).props('value')).toHaveLength(transactions.length);
  });
});
