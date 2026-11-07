<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { AddressFields, LocalityFields } from '@Interfaces';

type StatusTone = 'error' | 'success' | 'warning';
type TypeTone = 'danger' | 'info' | 'success' | 'warn';
type ToastSeverity = 'error' | 'info' | 'success' | 'warn';

interface ShowcaseTx extends Record<string, unknown> {
  id: string;
  amount: string;
  date: string;
  entity: string;
  status: string;
  statusTone: StatusTone;
  type: string;
  typeTone: TypeTone;
}

interface ShowcaseDataset {
  key: string;
  label: string;
  items: Record<string, unknown>[];
  title: { valueKey: string };
  amount: { valueKey: string; valueFormatter?: (raw: unknown) => string };
  badge: { valueKey: string; toneKey: string };
  badgeIconMap: Record<string, string>;
  status: { valueKey: string; toneKey: string };
  statusIconMap: Record<string, string>;
  subtitle: { valueKey: string };
}

const toast = useToast();
const { t } = useI18n();

/*
 * Sample rows carry a KEY for every word a person reads (status, type, priority…) and the
 * computeds below translate it. The OList* family prints what it is given and looks badge icons
 * up by the printed value, so the icon maps are keyed by the translated word too.
 */
const SAMPLE = 'showcase.components.sample';

const CRUD_ITEMS = [
  { id: 'c1', name: 'marketingPlan', owner: 'Alice', status: 'active', statusTone: 'success', durationMinutes: 12 },
  { id: 'c2', name: 'q3Budget', owner: 'Bob', status: 'draft', statusTone: 'warning', durationMinutes: 8 },
  { id: 'c3', name: 'onboardingGuide', owner: 'Carol', status: 'archived', statusTone: 'error', durationMinutes: 20 },
];

const INVENTORY_CATEGORY_ICON: Record<string, string> = {
  electronics: 'pi pi-desktop',
  furniture: 'pi pi-box',
  office: 'pi pi-briefcase',
};

const INVENTORY_ITEMS = [
  {
    id: 'i1',
    name: 'notebooks',
    quantity: 1200,
    category: 'office',
    categoryTone: 'primary',
    stock: 'inStock',
    stockTone: 'success',
    sku: 'OFF-NTB',
  },
  {
    id: 'i2',
    name: 'usbCables',
    quantity: 80,
    category: 'electronics',
    categoryTone: 'info',
    stock: 'low',
    stockTone: 'warning',
    sku: 'ELE-USB',
  },
  {
    id: 'i3',
    name: 'officeChairs',
    quantity: 0,
    category: 'furniture',
    categoryTone: 'warn',
    stock: 'out',
    stockTone: 'error',
    sku: 'FUR-CHR',
  },
  {
    id: 'i4',
    name: 'ballpointPens',
    quantity: 5000,
    category: 'office',
    categoryTone: 'primary',
    stock: 'inStock',
    stockTone: 'success',
    sku: 'OFF-PEN',
  },
];

const KANBAN_COLUMNS: { status: string; statusTone: StatusTone }[] = [
  { status: 'done', statusTone: 'success' },
  { status: 'scheduled', statusTone: 'warning' },
  { status: 'canceled', statusTone: 'error' },
];

const KANBAN_STATUS_BG: Record<StatusTone, string> = {
  error: 'bg-danger-50',
  success: 'bg-success-50',
  warning: 'bg-warn-50',
};

const KANBAN_STATUS_BORDER: Record<StatusTone, string> = {
  error: 'border-danger-200',
  success: 'border-success-200',
  warning: 'border-warn-200',
};

const KANBAN_STATUS_TEXT: Record<StatusTone, string> = {
  error: 'text-danger-700',
  success: 'text-success-700',
  warning: 'text-warn-700',
};

const MULTI_SELECT_OPTIONS = [
  { key: 'designSystem', value: 'design-system' },
  { key: 'themeTokens', value: 'theme-tokens' },
  { key: 'uxWriting', value: 'ux-writing' },
];

const SAMPLE_TRANSACTIONS = [
  {
    id: 'showcase-1',
    amount: 'R$ 5.000',
    date: '05/05/2026',
    entity: 'Battoni Dev',
    status: 'done',
    statusTone: 'success' as const,
    type: 'deposit',
    typeTone: 'success' as const,
  },
  {
    id: 'showcase-2',
    amount: 'R$ 1.250',
    date: '04/05/2026',
    entity: 'Acme Ltda',
    status: 'scheduled',
    statusTone: 'warning' as const,
    type: 'payment',
    typeTone: 'info' as const,
  },
  {
    id: 'showcase-3',
    amount: 'R$ 700',
    date: '03/05/2026',
    entity: 'Beta Corp',
    status: 'canceled',
    statusTone: 'error' as const,
    type: 'refund',
    typeTone: 'danger' as const,
  },
];

const SELECT_OPTIONS = [
  { key: 'design', value: 'design' },
  { key: 'engineering', value: 'engineering' },
  { key: 'product', value: 'product' },
];

const SHOWCASE_ITEMS: ShowcaseTx[] = [
  {
    id: 'sc-1',
    amount: 'R$ 100.000',
    date: '10/04/2026',
    entity: 'Battoni Dev',
    status: 'done',
    statusTone: 'success',
    type: 'deposit',
    typeTone: 'success',
  },
  {
    id: 'sc-2',
    amount: 'R$ 30.000',
    date: '10/04/2026',
    entity: 'Battoni Dev',
    status: 'canceled',
    statusTone: 'error',
    type: 'withdraw',
    typeTone: 'warn',
  },
  {
    id: 'sc-3',
    amount: 'R$ 20.000',
    date: '12/04/2026',
    entity: 'Globex',
    status: 'scheduled',
    statusTone: 'warning',
    type: 'payment',
    typeTone: 'info',
  },
  {
    id: 'sc-4',
    amount: 'R$ 10.000',
    date: '12/04/2026',
    entity: 'Initech',
    status: 'done',
    statusTone: 'success',
    type: 'refund',
    typeTone: 'danger',
  },
  {
    id: 'sc-5',
    amount: 'R$ 50.000',
    date: '15/04/2026',
    entity: 'Acme Corp',
    status: 'done',
    statusTone: 'success',
    type: 'deposit',
    typeTone: 'success',
  },
  {
    id: 'sc-6',
    amount: 'R$ 5.000',
    date: '15/04/2026',
    entity: 'Beta Ltda',
    status: 'scheduled',
    statusTone: 'warning',
    type: 'payment',
    typeTone: 'info',
  },
];

const TASK_ITEMS = [
  {
    id: 't1',
    name: 'designLandingPage',
    durationMinutes: 45,
    priority: 'high',
    priorityTone: 'danger',
    state: 'doing',
    stateTone: 'warning',
    assignee: 'Alice',
  },
  {
    id: 't2',
    name: 'writeApiDocs',
    durationMinutes: 30,
    priority: 'medium',
    priorityTone: 'warn',
    state: 'todo',
    stateTone: 'info',
    assignee: 'Bob',
  },
  {
    id: 't3',
    name: 'fixLoginBug',
    durationMinutes: 15,
    priority: 'high',
    priorityTone: 'danger',
    state: 'done',
    stateTone: 'success',
    assignee: 'Carol',
  },
  {
    id: 't4',
    name: 'reviewPullRequests',
    durationMinutes: 20,
    priority: 'low',
    priorityTone: 'info',
    state: 'done',
    stateTone: 'success',
    assignee: 'Dave',
  },
];

const TASK_PRIORITY_ICON: Record<string, string> = {
  high: 'pi pi-flag-fill',
  low: 'pi pi-minus',
  medium: 'pi pi-flag',
};

const TX_STATUS_CLASS: Record<StatusTone, string> = {
  error: 'celer-tag-soft-danger',
  success: 'celer-tag-soft-success',
  warning: 'celer-tag-soft-warn',
};

const TX_STATUS_ICON: Record<StatusTone, string> = {
  error: 'pi pi-ban',
  success: 'pi pi-check-circle',
  warning: 'pi pi-clock',
};

const TX_TYPE_CLASS: Record<TypeTone, string> = {
  danger: 'celer-tag-soft-danger',
  info: 'celer-tag-soft-info',
  success: 'celer-tag-soft-success',
  warn: 'celer-tag-soft-warn',
};

const TX_TYPE_ICON: Record<string, string> = {
  deposit: 'pi pi-arrow-down-left',
  payment: 'pi pi-credit-card',
  refund: 'pi pi-replay',
  withdraw: 'pi pi-arrow-up-right',
};

const dialogVisible = ref(false);
const inputValue = ref('Battoni Dev');
const multiSelectValue = ref<string[]>(['design-system']);
const passwordValue = ref('password123');
const sampleLocality = ref<LocalityFields>({ cep: '', city: '', region: '', state: '' });
const selectValue = ref('design');
const showcaseTab = ref('overview');
const textareaValue = ref(t('showcase.components.inputs.textareaValue'));

const sampleAddress = ref<AddressFields>({
  cep: '',
  city: '',
  complement: '',
  district: '',
  number: '',
  state: '',
  street: '',
});

const datasetKeyByDemo = reactive({
  divided: 'financial',
  cardGrid: 'financial',
  accentStrip: 'financial',
  statement: 'financial',
  feed: 'financial',
});

const sampleTransactions = computed(() => SAMPLE_TRANSACTIONS.map((item) => translateTransaction(item)));
const showcaseDatasets = computed(() => [financialDataset.value, tasksDataset.value, inventoryDataset.value]);
const showcaseItems = computed<ShowcaseTx[]>(() => SHOWCASE_ITEMS.map((item) => translateTransaction(item)));
const txTypeIcon = computed(() => translateKeys(TX_TYPE_ICON, `${SAMPLE}.types`));

const crudItems = computed(() =>
  CRUD_ITEMS.map((item) => ({
    ...item,
    name: t(`${SAMPLE}.crud.${item.name}`),
    status: t(`${SAMPLE}.crud.${item.status}`),
  }))
);

const datasetOptions = computed(() =>
  showcaseDatasets.value.map((dataset) => ({ label: dataset.label, value: dataset.key }))
);

const dateGroups = computed<{ date: string; items: ShowcaseTx[] }[]>(() => [
  { date: '10/04/2026', items: showcaseItems.value.slice(0, 2) },
  { date: '12/04/2026', items: showcaseItems.value.slice(2, 4) },
  { date: '15/04/2026', items: showcaseItems.value.slice(4, 6) },
]);

const financialDataset = computed<ShowcaseDataset>(() => ({
  key: 'financial',
  label: t('showcase.components.datasets.financial'),
  items: showcaseItems.value,
  title: { valueKey: 'entity' },
  amount: { valueKey: 'amount' },
  badge: { valueKey: 'type', toneKey: 'typeTone' },
  badgeIconMap: txTypeIcon.value,
  status: { valueKey: 'status', toneKey: 'statusTone' },
  statusIconMap: TX_STATUS_ICON,
  subtitle: { valueKey: 'date' },
}));

const inventoryDataset = computed<ShowcaseDataset>(() => ({
  key: 'inventory',
  label: t('showcase.components.datasets.inventory'),
  items: INVENTORY_ITEMS.map((item) => ({
    ...item,
    category: t(`${SAMPLE}.inventory.${item.category}`),
    name: t(`${SAMPLE}.inventory.${item.name}`),
    stock: t(`${SAMPLE}.inventory.${item.stock}`),
  })),
  title: { valueKey: 'name' },
  amount: { valueKey: 'quantity', valueFormatter: formatItems },
  badge: { valueKey: 'category', toneKey: 'categoryTone' },
  badgeIconMap: translateKeys(INVENTORY_CATEGORY_ICON, `${SAMPLE}.inventory`),
  status: { valueKey: 'stock', toneKey: 'stockTone' },
  statusIconMap: { success: 'pi pi-check-circle', warning: 'pi pi-exclamation-triangle', error: 'pi pi-times-circle' },
  subtitle: { valueKey: 'sku' },
}));

const kanbanColumns = computed(() =>
  KANBAN_COLUMNS.map((column) => ({
    label: t(`${SAMPLE}.statuses.${column.status}`),
    statusTone: column.statusTone,
    items: showcaseItems.value.filter((item) => item.statusTone === column.statusTone),
  }))
);

const multiSelectOptions = computed(() =>
  MULTI_SELECT_OPTIONS.map(({ key, value }) => ({
    label: t(`showcase.components.inputs.multiSelectOptions.${key}`),
    value,
  }))
);

const selectOptions = computed(() =>
  SELECT_OPTIONS.map(({ key, value }) => ({ label: t(`showcase.components.inputs.selectOptions.${key}`), value }))
);

const tasksDataset = computed<ShowcaseDataset>(() => ({
  key: 'tasks',
  label: t('showcase.components.datasets.tasks'),
  items: TASK_ITEMS.map((item) => ({
    ...item,
    name: t(`${SAMPLE}.tasks.${item.name}`),
    priority: t(`${SAMPLE}.tasks.${item.priority}`),
    state: t(`${SAMPLE}.tasks.${item.state}`),
  })),
  title: { valueKey: 'name' },
  amount: { valueKey: 'durationMinutes', valueFormatter: formatMinutes },
  badge: { valueKey: 'priority', toneKey: 'priorityTone' },
  badgeIconMap: translateKeys(TASK_PRIORITY_ICON, `${SAMPLE}.tasks`),
  status: { valueKey: 'state', toneKey: 'stateTone' },
  statusIconMap: { success: 'pi pi-check-circle', warning: 'pi pi-clock', info: 'pi pi-hourglass' },
  subtitle: { valueKey: 'assignee' },
}));

// HELPERS
function datasetFor(demo: keyof typeof datasetKeyByDemo): ShowcaseDataset {
  return showcaseDatasets.value.find((dataset) => dataset.key === datasetKeyByDemo[demo]) ?? financialDataset.value;
}

function formatItems(raw: unknown): string {
  return t('showcase.components.units.items', { count: raw });
}

function formatMinutes(raw: unknown): string {
  return t('showcase.components.units.minutes', { count: raw });
}

function translateKeys(iconByKey: Record<string, string>, prefix: string): Record<string, string> {
  return Object.fromEntries(Object.entries(iconByKey).map(([key, icon]) => [t(`${prefix}.${key}`), icon]));
}

function translateTransaction<Transaction extends { status: string; type: string }>(item: Transaction): Transaction {
  return { ...item, status: t(`${SAMPLE}.statuses.${item.status}`), type: t(`${SAMPLE}.types.${item.type}`) };
}

// EVENTS
function onCrudDelete(item: Record<string, unknown>) {
  const action = t('common.actions.delete');

  toast.add({
    severity: 'error',
    summary: action,
    detail: t('showcase.components.crud.itemDetail', { action, name: item.name }),
    life: 3000,
  });
}

function onCrudEdit(item: Record<string, unknown>) {
  const action = t('common.actions.edit');

  toast.add({
    severity: 'info',
    summary: action,
    detail: t('showcase.components.crud.itemDetail', { action, name: item.name }),
    life: 3000,
  });
}

function onCrudHistory(item: Record<string, unknown>) {
  toast.add({
    severity: 'info',
    summary: t('showcase.components.crud.history'),
    detail: t('showcase.components.crud.historyDetail', { name: item.name }),
    life: 3000,
  });
}

function onTabEdit() {
  toast.add({
    severity: 'info',
    summary: t('showcase.components.tabs.editSummary'),
    detail: t('showcase.components.tabs.editDetail'),
    life: 3000,
  });
}

function showToast(severity: ToastSeverity) {
  toast.add({
    severity,
    summary: t('showcase.components.toast.summary', { severity }),
    detail: t('showcase.components.toast.detail'),
    life: 3500,
  });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="showcase.components.title" />
    </template>

    <div class="flex flex-col gap-8">
      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.typography') }}</h2>

        <div class="flex flex-col gap-3">
          <h1 class="text-heading text-3xl font-semibold">{{ $t('showcase.components.typography.heading1') }}</h1>

          <h2 class="text-heading text-2xl font-semibold">{{ $t('showcase.components.typography.heading2') }}</h2>

          <h3 class="text-heading text-xl font-semibold">{{ $t('showcase.components.typography.heading3') }}</h3>

          <h4 class="text-heading text-lg font-semibold">{{ $t('showcase.components.typography.heading4') }}</h4>

          <p class="text-heading text-base">{{ $t('showcase.components.typography.body') }}</p>

          <p class="text-muted text-sm">{{ $t('showcase.components.typography.label') }}</p>

          <p class="text-muted text-sm">{{ $t('showcase.components.typography.muted') }}</p>
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.buttons') }}</h2>

        <div class="flex flex-wrap items-center gap-3">
          <Button :label="$t('showcase.components.buttons.primary')" />

          <Button
            severity="secondary"
            :label="$t('showcase.components.buttons.secondary')"
          />

          <Button
            severity="success"
            :label="$t('showcase.components.buttons.success')"
          />

          <Button
            severity="warn"
            :label="$t('showcase.components.buttons.warning')"
          />

          <Button
            severity="danger"
            :label="$t('showcase.components.buttons.danger')"
          />

          <Button
            severity="info"
            :label="$t('showcase.components.buttons.info')"
          />

          <Button
            variant="outlined"
            :label="$t('showcase.components.buttons.outlined')"
          />

          <Button
            text
            :label="$t('showcase.components.buttons.text')"
          />

          <Button
            icon="pi pi-plus"
            :label="$t('showcase.components.buttons.withIcon')"
          />

          <Button
            disabled
            :label="$t('showcase.components.buttons.disabled')"
          />
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.tabs') }}</h2>

        <OTabView v-model="showcaseTab">
          <OTabPanel
            title="showcase.components.tabs.overview"
            value="overview"
          >
            <template #actions>
              <Button
                text
                icon="pi pi-pencil"
                size="small"
                @click="onTabEdit"
              />
            </template>

            <p class="text-muted text-sm">{{ $t('showcase.components.tabs.overviewBody') }}</p>
          </OTabPanel>

          <OTabPanel
            icon="pi pi-bolt"
            title="showcase.components.tabs.activity"
            value="activity"
          >
            <p class="text-muted text-sm">{{ $t('showcase.components.tabs.activityBody') }}</p>
          </OTabPanel>
        </OTabView>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.formInputs') }}</h2>

        <form class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="showcase-inputtext"
            >
              InputText
            </label>

            <InputText
              v-model="inputValue"
              id="showcase-inputtext"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="showcase-password"
            >
              Password
            </label>

            <Password
              v-model="passwordValue"
              toggleMask
              inputId="showcase-password"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="showcase-select"
            >
              Select
            </label>

            <Select
              v-model="selectValue"
              inputId="showcase-select"
              optionLabel="label"
              optionValue="value"
              :options="selectOptions"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="showcase-multiselect"
            >
              MultiSelect
            </label>

            <MultiSelect
              v-model="multiSelectValue"
              display="chip"
              inputId="showcase-multiselect"
              optionLabel="label"
              optionValue="value"
              :options="multiSelectOptions"
            />
          </div>

          <div class="flex flex-col gap-2 lg:col-span-2">
            <label
              class="text-muted mb-2 block text-sm"
              for="showcase-textarea"
            >
              Textarea
            </label>

            <Textarea
              v-model="textareaValue"
              id="showcase-textarea"
              rows="4"
            />
          </div>
        </form>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.formValidation') }}</h2>

        <div class="flex flex-col gap-4">
          <AFormError :error="$t('showcase.components.validation.required')" />

          <AFormError :error="$t('showcase.components.validation.unexpected')" />
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.address') }}</h2>

        <p class="text-muted text-sm">{{ $t('showcase.components.addressDescription') }}</p>

        <form class="max-w-xl">
          <MAddressFields v-model="sampleAddress" />
        </form>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.locality') }}</h2>

        <p class="text-muted text-sm">{{ $t('showcase.components.localityDescription') }}</p>

        <form class="max-w-xl">
          <MLocalityFields
            v-model="sampleLocality"
            showRegion
          />
        </form>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.cards') }}</h2>

        <div class="3xl:grid-cols-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <MWalletCard
            value="R$ 18.000"
            variant="primary"
            :description="$t('showcase.components.cards.primary.description')"
            :title="$t('showcase.components.cards.primary.title')"
          />

          <MWalletCard
            value="R$ 14.500"
            variant="info"
            :description="$t('showcase.components.cards.info.description')"
            :title="$t('showcase.components.cards.info.title')"
          />

          <MWalletCard
            value="R$ 9.200"
            variant="success"
            :description="$t('showcase.components.cards.success.description')"
            :title="$t('showcase.components.cards.success.title')"
          />

          <MWalletCard
            value="R$ 4.300"
            variant="warning"
            :description="$t('showcase.components.cards.warning.description')"
            :title="$t('showcase.components.cards.warning.title')"
          />
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.dataTable') }}</h2>

        <OWalletTransactions :transactions="sampleTransactions" />
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.dialog') }}</h2>

        <div class="flex items-center gap-3">
          <Button
            :label="$t('showcase.components.dialog.open')"
            @click="dialogVisible = true"
          />
        </div>

        <aside class="contents">
          <MMainDialog
            v-model:visible="dialogVisible"
            isFooterless
          >
            <div class="flex flex-col gap-4">
              <p class="text-muted text-sm">{{ $t('showcase.components.dialog.body') }}</p>

              <div class="flex justify-end">
                <Button
                  severity="secondary"
                  variant="outlined"
                  :label="$t('showcase.components.dialog.close')"
                  @click="dialogVisible = false"
                />
              </div>
            </div>
          </MMainDialog>
        </aside>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.toast') }}</h2>

        <div class="flex flex-wrap items-center gap-3">
          <Button
            severity="success"
            :label="$t('showcase.components.toast.success')"
            @click="showToast('success')"
          />

          <Button
            severity="info"
            :label="$t('showcase.components.toast.info')"
            @click="showToast('info')"
          />

          <Button
            severity="warn"
            :label="$t('showcase.components.toast.warn')"
            @click="showToast('warn')"
          />

          <Button
            severity="danger"
            :label="$t('showcase.components.toast.error')"
            @click="showToast('error')"
          />
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.messages') }}</h2>

        <div class="flex flex-col gap-3">
          <Message severity="success">{{ $t('showcase.components.messages.success') }}</Message>

          <Message severity="info">{{ $t('showcase.components.messages.info') }}</Message>

          <Message severity="warn">{{ $t('showcase.components.messages.warn') }}</Message>

          <Message severity="error">{{ $t('showcase.components.messages.error') }}</Message>
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.tagsAndBadges') }}</h2>

        <div class="flex flex-wrap items-center gap-3">
          <Tag
            severity="success"
            :value="$t('showcase.components.tags.success')"
          />

          <Tag
            severity="info"
            :value="$t('showcase.components.tags.info')"
          />

          <Tag
            severity="warn"
            :value="$t('showcase.components.tags.warn')"
          />

          <Tag
            severity="danger"
            :value="$t('showcase.components.tags.danger')"
          />

          <Badge
            severity="success"
            value="7"
          />

          <Badge
            severity="info"
            value="12"
          />

          <Badge
            severity="warn"
            value="3"
          />

          <Badge
            severity="danger"
            value="1"
          />
        </div>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.crudList') }}</h2>

        <OListDivided
          canDelete
          canEdit
          showDetails
          :amount="{ valueKey: 'durationMinutes', valueFormatter: formatMinutes }"
          :items="crudItems"
          :status="{ valueKey: 'status', toneKey: 'statusTone' }"
          :title="{ valueKey: 'name' }"
          @onDelete="onCrudDelete"
          @onEdit="onCrudEdit"
        >
          <template #actions="{ item }">
            <Button
              rounded
              text
              class="h-7 w-7 shrink-0"
              icon="pi pi-history"
              severity="secondary"
              @click="onCrudHistory(item)"
            />
          </template>

          <template #details="{ item }">
            <div class="flex flex-col gap-2">
              <p class="text-muted text-sm">{{ $t('showcase.components.crud.owner', { owner: item.owner }) }}</p>

              <p class="text-muted text-sm">
                {{ $t('showcase.components.crud.duration', { minutes: item.durationMinutes }) }}
              </p>
            </div>
          </template>
        </OListDivided>
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.dividedList') }}</h2>

          <SelectButton
            v-model="datasetKeyByDemo.divided"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
            :options="datasetOptions"
          />
        </div>

        <OListDivided
          showDetails
          :amount="datasetFor('divided').amount"
          :badge="datasetFor('divided').badge"
          :items="datasetFor('divided').items"
          :status="datasetFor('divided').status"
          :statusIconMap="datasetFor('divided').statusIconMap"
          :subtitle="datasetFor('divided').subtitle"
          :title="datasetFor('divided').title"
        />
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.cardGrid') }}</h2>

          <SelectButton
            v-model="datasetKeyByDemo.cardGrid"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
            :options="datasetOptions"
          />
        </div>

        <OListCardGrid
          showDetails
          :amount="datasetFor('cardGrid').amount"
          :badge="datasetFor('cardGrid').badge"
          :items="datasetFor('cardGrid').items"
          :status="datasetFor('cardGrid').status"
          :statusIconMap="datasetFor('cardGrid').statusIconMap"
          :subtitle="datasetFor('cardGrid').subtitle"
          :title="datasetFor('cardGrid').title"
        />
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.accentStrip') }}</h2>

          <SelectButton
            v-model="datasetKeyByDemo.accentStrip"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
            :options="datasetOptions"
          />
        </div>

        <OListAccentStrip
          showDetails
          :amount="datasetFor('accentStrip').amount"
          :badge="datasetFor('accentStrip').badge"
          :items="datasetFor('accentStrip').items"
          :status="datasetFor('accentStrip').status"
          :statusIconMap="datasetFor('accentStrip').statusIconMap"
          :subtitle="datasetFor('accentStrip').subtitle"
          :title="datasetFor('accentStrip').title"
        />
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.statement') }}</h2>

          <SelectButton
            v-model="datasetKeyByDemo.statement"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
            :options="datasetOptions"
          />
        </div>

        <OListStatement
          showDetails
          :amount="datasetFor('statement').amount"
          :badge="datasetFor('statement').badge"
          :items="datasetFor('statement').items"
          :status="datasetFor('statement').status"
          :statusIconMap="datasetFor('statement').statusIconMap"
          :subtitle="datasetFor('statement').subtitle"
          :title="datasetFor('statement').title"
        />
      </section>

      <section class="border-line bg-canvas flex flex-col gap-4 rounded-lg border p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.feed') }}</h2>

          <SelectButton
            v-model="datasetKeyByDemo.feed"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
            :options="datasetOptions"
          />
        </div>

        <OListFeed
          showDetails
          :amount="datasetFor('feed').amount"
          :badge="datasetFor('feed').badge"
          :badgeIconMap="datasetFor('feed').badgeIconMap"
          :items="datasetFor('feed').items"
          :status="datasetFor('feed').status"
          :statusIconMap="datasetFor('feed').statusIconMap"
          :subtitle="datasetFor('feed').subtitle"
          :title="datasetFor('feed').title"
        />
      </section>

      <section class="flex flex-col gap-6">
        <div class="flex items-center gap-3">
          <h2 class="text-heading text-lg font-semibold">{{ $t('showcase.components.sections.inDevelopment') }}</h2>

          <Tag
            severity="warn"
            :value="$t('showcase.components.comingSoon')"
          />
        </div>

        <div class="flex flex-col gap-8">
          <div class="flex flex-col gap-3">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">
              {{ $t('showcase.components.sections.groupedByDate') }}
            </p>

            <div class="flex flex-col gap-5">
              <div
                v-for="group in dateGroups"
                class="flex flex-col gap-2"
                :key="group.date"
              >
                <p class="text-muted text-xs font-semibold tracking-wider uppercase">{{ group.date }}</p>

                <div class="border-line bg-canvas dark:bg-panel overflow-hidden rounded-xl border">
                  <div
                    v-for="item in group.items"
                    class="border-line flex items-center justify-between gap-4 border-b px-4 py-3 last:border-b-0"
                    :key="item.id"
                  >
                    <div class="flex min-w-0 items-center gap-3">
                      <Tag
                        rounded
                        :class="TX_TYPE_CLASS[item.typeTone]"
                        :value="item.type"
                      />

                      <span class="text-heading truncate text-sm">{{ item.entity }}</span>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                      <span class="text-heading text-sm font-semibold">{{ item.amount }}</span>

                      <Tag
                        rounded
                        :class="TX_STATUS_CLASS[item.statusTone]"
                        :value="item.status"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-3">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">
              {{ $t('showcase.components.sections.labelValueGrid') }}
            </p>

            <div class="border-line bg-canvas dark:bg-panel overflow-hidden rounded-xl border">
              <div
                v-for="item in showcaseItems"
                class="border-line grid grid-cols-2 gap-3 border-b px-4 py-3 last:border-b-0 sm:grid-cols-5"
                :key="item.id"
              >
                <div class="flex flex-col gap-1">
                  <span class="text-muted text-xs">{{ $t('showcase.components.columns.type') }}</span>

                  <Tag
                    rounded
                    :class="TX_TYPE_CLASS[item.typeTone]"
                    :value="item.type"
                  />
                </div>

                <div class="flex flex-col gap-1">
                  <span class="text-muted text-xs">{{ $t('showcase.components.columns.entity') }}</span>

                  <span class="text-heading text-sm font-medium">{{ item.entity }}</span>
                </div>

                <div class="flex flex-col gap-1">
                  <span class="text-muted text-xs">{{ $t('showcase.components.columns.amount') }}</span>

                  <span class="text-heading text-sm font-semibold">{{ item.amount }}</span>
                </div>

                <div class="flex flex-col gap-1">
                  <span class="text-muted text-xs">{{ $t('showcase.components.columns.date') }}</span>

                  <span class="text-muted text-sm">{{ item.date }}</span>
                </div>

                <div class="flex flex-col gap-1">
                  <span class="text-muted text-xs">{{ $t('showcase.components.columns.status') }}</span>

                  <Tag
                    rounded
                    :class="TX_STATUS_CLASS[item.statusTone]"
                    :icon="TX_STATUS_ICON[item.statusTone]"
                    :value="item.status"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-3">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">
              {{ $t('showcase.components.sections.amountHero') }}
            </p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <div
                v-for="item in showcaseItems"
                class="border-line bg-canvas dark:bg-panel flex flex-col items-center gap-2 rounded-xl border p-4 text-center"
                :key="item.id"
              >
                <Tag
                  rounded
                  :class="TX_TYPE_CLASS[item.typeTone]"
                  :value="item.type"
                />

                <p class="text-heading text-2xl font-bold">{{ item.amount }}</p>

                <p class="text-heading text-sm font-medium">{{ item.entity }}</p>

                <div class="flex items-center gap-2">
                  <span class="text-muted text-xs">{{ item.date }}</span>

                  <Tag
                    rounded
                    :class="TX_STATUS_CLASS[item.statusTone]"
                    :value="item.status"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-3">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">
              {{ $t('showcase.components.sections.gridTable') }}
            </p>

            <div class="border-line bg-canvas dark:bg-panel overflow-hidden rounded-xl border">
              <div class="border-line bg-canvas dark:bg-panel hidden grid-cols-5 border-b px-4 py-2 sm:grid">
                <span class="text-heading text-xs font-semibold">{{ $t('showcase.components.columns.type') }}</span>

                <span class="text-heading text-xs font-semibold">{{ $t('showcase.components.columns.entity') }}</span>

                <span class="text-heading text-xs font-semibold">{{ $t('showcase.components.columns.amount') }}</span>

                <span class="text-heading text-xs font-semibold">{{ $t('showcase.components.columns.date') }}</span>

                <span class="text-heading text-xs font-semibold">{{ $t('showcase.components.columns.status') }}</span>
              </div>

              <div
                v-for="item in showcaseItems"
                class="border-line border-b last:border-b-0"
                :key="item.id"
              >
                <div class="flex items-center justify-between gap-3 px-4 py-3 sm:hidden">
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Tag
                      rounded
                      :class="TX_TYPE_CLASS[item.typeTone]"
                      :value="item.type"
                    />

                    <span class="text-heading truncate text-sm">{{ item.entity }}</span>

                    <span class="text-muted text-xs">{{ item.date }}</span>
                  </div>

                  <div class="flex shrink-0 flex-col items-end gap-1.5">
                    <span class="text-heading font-semibold">{{ item.amount }}</span>

                    <Tag
                      rounded
                      :class="TX_STATUS_CLASS[item.statusTone]"
                      :value="item.status"
                    />
                  </div>
                </div>

                <div class="hidden grid-cols-5 items-center gap-3 px-4 py-3 sm:grid">
                  <Tag
                    rounded
                    :class="TX_TYPE_CLASS[item.typeTone]"
                    :value="item.type"
                  />

                  <span class="text-heading text-sm">{{ item.entity }}</span>

                  <span class="text-heading text-sm font-semibold">{{ item.amount }}</span>

                  <span class="text-muted text-sm">{{ item.date }}</span>

                  <Tag
                    rounded
                    :class="TX_STATUS_CLASS[item.statusTone]"
                    :icon="TX_STATUS_ICON[item.statusTone]"
                    :value="item.status"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-3">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">
              {{ $t('showcase.components.sections.kanban') }}
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div
                v-for="column in kanbanColumns"
                :key="column.statusTone"
                :class="['flex flex-col overflow-hidden rounded-xl border', KANBAN_STATUS_BORDER[column.statusTone]]"
              >
                <div :class="['flex items-center justify-between px-4 py-3', KANBAN_STATUS_BG[column.statusTone]]">
                  <span :class="['text-sm font-semibold', KANBAN_STATUS_TEXT[column.statusTone]]">
                    {{ column.label }}
                  </span>

                  <span :class="['text-xs font-medium', KANBAN_STATUS_TEXT[column.statusTone]]">
                    {{ column.items.length }}
                  </span>
                </div>

                <div class="divide-line bg-canvas dark:bg-panel flex flex-col divide-y">
                  <div
                    v-for="item in column.items"
                    class="flex flex-col gap-2 px-4 py-3"
                    :key="item.id"
                  >
                    <div class="flex items-start justify-between gap-2">
                      <Tag
                        rounded
                        :class="TX_TYPE_CLASS[item.typeTone]"
                        :value="item.type"
                      />

                      <span class="text-heading text-sm font-semibold">{{ item.amount }}</span>
                    </div>

                    <div>
                      <p class="text-heading text-sm font-medium">{{ item.entity }}</p>

                      <p class="text-muted text-xs">{{ item.date }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </TheLayout>
</template>
