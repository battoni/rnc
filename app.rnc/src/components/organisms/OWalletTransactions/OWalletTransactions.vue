<script setup lang="ts">
interface WalletTransactionItem {
  id: string;
  amount: string;
  date: string;
  entity: string;
  status: string;
  statusTone: 'error' | 'success' | 'warning';
  type: string;
  typeTone: 'danger' | 'info' | 'primary' | 'success' | 'warn';
}

defineProps<{
  transactions: WalletTransactionItem[];
}>();

const statusClassByTone = {
  error: 'celer-tag-soft-danger',
  success: 'celer-tag-soft-success',
  warning: 'celer-tag-soft-warn',
};

const statusIconByTone = {
  error: 'pi pi-ban',
  success: 'pi pi-check-circle',
  warning: 'pi pi-clock',
};

const typeClassByTone = {
  danger: 'celer-tag-soft-danger',
  info: 'celer-tag-soft-info',
  primary: 'celer-tag-soft-primary',
  success: 'celer-tag-soft-success',
  warn: 'celer-tag-soft-warn',
};
const columnBodyClass = 'border-s-0 border-e-0 px-8 py-4 text-base text-body';
const columnHeaderClass = 'border-s-0 border-e-0 bg-panel px-8 py-4 text-sm font-semibold text-heading';

function getStatusClass(tone: WalletTransactionItem['statusTone']) {
  return statusClassByTone[tone];
}

function getStatusIcon(tone: WalletTransactionItem['statusTone']) {
  return statusIconByTone[tone];
}

function getTypeClass(tone: WalletTransactionItem['typeTone']) {
  return typeClassByTone[tone];
}
</script>

<template>
  <DataTable
    scrollable
    stripedRows
    class="border-line-strong bg-panel h-auto max-h-96 min-h-0 overflow-hidden rounded-lg border md:max-h-full lg:max-h-full"
    dataKey="id"
    pt:header="p-0"
    scrollHeight="flex"
    :value="transactions"
  >
    <template #empty>
      <div class="text-muted px-8 py-10 text-center text-base">
        {{ $t('finance.transactions.empty') }}
      </div>
    </template>

    <template #header>
      <div class="border-line bg-panel flex items-center justify-between border-b px-8 py-5">
        <h2 class="text-heading text-lg font-semibold sm:text-2xl">{{ $t('finance.transactions.title') }}</h2>

        <Button
          link
          class="celer-button-link-primary"
          :label="$t('finance.transactions.viewAll')"
        />
      </div>
    </template>

    <Column
      sortable
      field="type"
      :bodyClass="columnBodyClass"
      :header="$t('finance.transactions.columns.type')"
      :headerClass="columnHeaderClass"
    >
      <template #body="{ data }">
        <Tag
          rounded
          :class="getTypeClass(data.typeTone)"
          :value="data.type"
        />
      </template>
    </Column>

    <Column
      sortable
      field="entity"
      :bodyClass="columnBodyClass"
      :header="$t('finance.transactions.columns.entity')"
      :headerClass="columnHeaderClass"
    />

    <Column
      sortable
      field="amount"
      :bodyClass="columnBodyClass"
      :header="$t('finance.transactions.columns.amount')"
      :headerClass="columnHeaderClass"
    />

    <Column
      sortable
      field="date"
      :bodyClass="columnBodyClass"
      :header="$t('finance.transactions.columns.date')"
      :headerClass="columnHeaderClass"
    />

    <Column
      sortable
      field="status"
      :bodyClass="columnBodyClass"
      :header="$t('finance.transactions.columns.status')"
      :headerClass="columnHeaderClass"
    >
      <template #body="{ data }">
        <Tag
          rounded
          :class="getStatusClass(data.statusTone)"
          :icon="getStatusIcon(data.statusTone)"
          :value="data.status"
        />
      </template>
    </Column>
  </DataTable>
</template>
