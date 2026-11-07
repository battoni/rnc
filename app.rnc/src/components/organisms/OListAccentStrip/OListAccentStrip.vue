<script setup lang="ts">
import { computed, ref, useSlots, watch } from 'vue';
import { hasSlotContent } from '@Helpers';

interface AmountFieldConfig {
  align?: 'left' | 'right';
  valueFormatter?: (raw: unknown) => string;
  valueKey: string;
}

interface TagFieldConfig {
  toneKey: string;
  valueKey: string;
}

interface TextFieldConfig {
  valueKey: string;
}

const emit = defineEmits<{
  onArchive: [item: Record<string, unknown>];
  onDelete: [item: Record<string, unknown>];
  onEdit: [item: Record<string, unknown>];
}>();

const props = withDefaults(
  defineProps<{
    amount?: AmountFieldConfig;
    badge?: TagFieldConfig;
    canArchive?: boolean;
    canDelete?: boolean;
    canEdit?: boolean;
    emptyMessage?: string;
    itemKey?: string;
    items: Record<string, unknown>[];
    showDetails?: boolean;
    status?: TagFieldConfig;
    statusIconMap?: Record<string, string>;
    subtitle?: TextFieldConfig;
    title: TextFieldConfig;
  }>(),
  {
    canArchive: false,
    canDelete: false,
    canEdit: false,
    emptyMessage: 'common.noItems',
    itemKey: 'id',
    showDetails: false,
  }
);

const slots = useSlots();

const borderByTone: Record<string, string> = {
  contrast: 'border-contrast-400',
  danger: 'border-danger-400',
  error: 'border-danger-400',
  info: 'border-info-400',
  primary: 'border-primary-400',
  success: 'border-success-400',
  warn: 'border-warn-400',
  warning: 'border-warn-400',
};

const tagClassByTone: Record<string, string> = {
  contrast: 'celer-tag-soft-contrast',
  danger: 'celer-tag-soft-danger',
  error: 'celer-tag-soft-danger',
  info: 'celer-tag-soft-info',
  primary: 'celer-tag-soft-primary',
  success: 'celer-tag-soft-success',
  warn: 'celer-tag-soft-warn',
  warning: 'celer-tag-soft-warn',
};

const detailDialogVisible = ref(false);
const selectedItem = ref<Record<string, unknown> | null>(null);

const hasBuiltInActions = computed(() => props.canEdit || props.canArchive || props.canDelete);

watch(detailDialogVisible, onDetailVisibleChange);

// HELPERS
function accentBorderClass(item: Record<string, unknown>): string {
  if (!props.badge) return '';

  return borderByTone[fieldValue(item, props.badge.toneKey)] ?? '';
}

function amountValue(item: Record<string, unknown>): string {
  if (!props.amount) return '';

  const raw = item[props.amount.valueKey];
  return props.amount.valueFormatter ? props.amount.valueFormatter(raw) : String(raw ?? '');
}

function closeDetail() {
  detailDialogVisible.value = false;
}

function fieldValue(item: Record<string, unknown>, key: string): string {
  return String(item[key] ?? '');
}

function hasItemActions(item: Record<string, unknown>): boolean {
  if (hasBuiltInActions.value) return true;

  /*
   * Asked per item, not per list: a mounted `#actions` slot whose buttons each failed their own
   * `v-if` renders a Comment, and reading `!!slots.actions` gave the detail dialog a footer with
   * nothing in it. `hasSlotContent` asks what the slot actually drew for this row.
   */
  return hasSlotContent(slots.actions?.({ close: closeDetail, item }));
}

function tagClass(item: Record<string, unknown>, config: TagFieldConfig): string {
  return tagClassByTone[fieldValue(item, config.toneKey)] ?? '';
}

// EVENTS
function onArchiveClick(item: Record<string, unknown> | null) {
  if (!item) return;

  detailDialogVisible.value = false;
  emit('onArchive', item);
}

function onDeleteClick(item: Record<string, unknown> | null) {
  if (!item) return;

  detailDialogVisible.value = false;
  emit('onDelete', item);
}

function onDetailOpen(item: Record<string, unknown>) {
  selectedItem.value = item;
  detailDialogVisible.value = true;
}

function onDetailVisibleChange(visible: boolean) {
  if (!visible) selectedItem.value = null;
}

function onEditClick(item: Record<string, unknown> | null) {
  if (!item) return;

  detailDialogVisible.value = false;
  emit('onEdit', item);
}
</script>

<template>
  <AEmptyState
    v-if="!items.length"
    :description="$t(emptyMessage)"
  />

  <div
    v-else
    class="flex flex-col gap-2"
  >
    <div
      v-for="item in items"
      :key="fieldValue(item, itemKey)"
      :class="[
        'bg-panel flex items-center justify-between gap-4 rounded-r-xl border-l-4 px-4 py-3',
        accentBorderClass(item),
      ]"
    >
      <div class="flex min-w-0 flex-col gap-0.5">
        <span class="text-heading text-sm font-medium">{{ fieldValue(item, title.valueKey) }}</span>

        <span class="text-muted text-xs">
          <template v-if="badge">{{ fieldValue(item, badge.valueKey) }}</template>
          <template v-if="badge && subtitle"> · </template>
          <template v-if="subtitle">{{ fieldValue(item, subtitle.valueKey) }}</template>
        </span>
      </div>

      <div class="flex shrink-0 items-center gap-3">
        <span
          v-if="amount"
          :class="['text-heading mr-2 text-sm font-semibold', amount.align === 'left' ? 'text-left' : 'text-right']"
        >
          {{ amountValue(item) }}
        </span>

        <Tag
          v-if="status"
          rounded
          :class="['mr-1', tagClass(item, status)]"
          :icon="statusIconMap?.[fieldValue(item, status.toneKey)]"
          :value="fieldValue(item, status.valueKey)"
        />

        <Button
          v-if="canEdit"
          rounded
          text
          class="h-7 w-7 shrink-0 pointer-coarse:h-11 pointer-coarse:w-11"
          icon="pi pi-pencil"
          severity="secondary"
          :aria-label="$t('common.actions.edit')"
          @click="onEditClick(item)"
        />

        <Button
          v-if="canArchive"
          rounded
          text
          class="h-7 w-7 shrink-0 pointer-coarse:h-11 pointer-coarse:w-11"
          icon="pi pi-inbox"
          severity="secondary"
          :aria-label="$t('common.actions.archive')"
          @click="onArchiveClick(item)"
        />

        <Button
          v-if="canDelete"
          rounded
          text
          class="h-7 w-7 shrink-0 pointer-coarse:h-11 pointer-coarse:w-11"
          icon="pi pi-trash"
          severity="danger"
          :aria-label="$t('common.actions.delete')"
          @click="onDeleteClick(item)"
        />

        <slot
          name="actions"
          :close="closeDetail"
          :item
        />

        <Button
          v-if="showDetails"
          rounded
          text
          class="h-7 w-7 shrink-0 pointer-coarse:h-11 pointer-coarse:w-11"
          icon="pi pi-eye"
          severity="secondary"
          :aria-label="$t('common.details')"
          @click="onDetailOpen(item)"
        />
      </div>
    </div>
  </div>

  <aside class="contents">
    <MMainDialog
      v-model:visible="detailDialogVisible"
      title="common.details"
      :isFooterless="!selectedItem || !hasItemActions(selectedItem)"
    >
      <slot
        v-if="selectedItem"
        name="details"
        :item="selectedItem"
      />

      <template #footer>
        <Button
          v-if="canEdit"
          icon="pi pi-pencil"
          :label="$t('common.actions.edit')"
          @click="onEditClick(selectedItem)"
        />

        <Button
          v-if="canArchive"
          icon="pi pi-inbox"
          severity="secondary"
          :label="$t('common.actions.archive')"
          @click="onArchiveClick(selectedItem)"
        />

        <Button
          v-if="canDelete"
          icon="pi pi-trash"
          severity="danger"
          :label="$t('common.actions.delete')"
          @click="onDeleteClick(selectedItem)"
        />

        <slot
          v-if="selectedItem"
          name="actions"
          :close="closeDetail"
          :item="selectedItem"
        />
      </template>
    </MMainDialog>
  </aside>
</template>
