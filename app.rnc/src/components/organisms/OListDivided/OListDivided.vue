<script setup lang="ts">
import { computed, ref, useSlots, useTemplateRef, watch } from 'vue';
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

const actionsMenuRef = useTemplateRef<{ hide: () => void; toggle: (event: Event) => void }>('actionsMenuRef');

const actionsMenuItem = ref<Record<string, unknown> | null>(null);
const detailDialogVisible = ref(false);
const selectedItem = ref<Record<string, unknown> | null>(null);

const hasBuiltInActions = computed(() => props.canEdit || props.canArchive || props.canDelete);

watch(detailDialogVisible, onDetailVisibleChange);

// HELPERS
function amountValue(item: Record<string, unknown>): string {
  if (!props.amount) return '';

  const raw = item[props.amount.valueKey];
  return props.amount.valueFormatter ? props.amount.valueFormatter(raw) : String(raw ?? '');
}

function closeActionsMenu() {
  actionsMenuRef.value?.hide();
}

function closeDetail() {
  detailDialogVisible.value = false;
}

function closeMenuAndDetail() {
  closeActionsMenu();
  closeDetail();
}

function fieldValue(item: Record<string, unknown>, key: string): string {
  return String(item[key] ?? '');
}

/**
 * Does THIS row have anything to act on, in this variant?
 *
 * Asked per row, not per list. A mounted `#actions` slot used to be enough to light the ⋮, and a
 * slot whose every button failed its own `v-if` — the archive hidden on the viewer's own row, the
 * delete hidden on an active account — left a menu that opened empty. So the slot is rendered for
 * the item and its output is inspected (`hasSlotContent`); the built-in `can*` buttons are the
 * same on every row, so they answer first and cost nothing.
 */
function hasItemActions(item: Record<string, unknown>, variant: 'inline' | 'menu'): boolean {
  if (hasBuiltInActions.value) return true;

  const close = variant === 'menu' ? closeMenuAndDetail : closeDetail;

  return hasSlotContent(slots.actions?.({ close, item, variant }));
}

function hasRowControls(item: Record<string, unknown>, variant: 'inline' | 'menu'): boolean {
  return props.showDetails || hasItemActions(item, variant);
}

function tagClass(item: Record<string, unknown>, config: TagFieldConfig): string {
  return tagClassByTone[fieldValue(item, config.toneKey)] ?? '';
}

// EVENTS
function onActionsMenuHide() {
  actionsMenuItem.value = null;
}

function onActionsMenuToggle(event: Event, item: Record<string, unknown>) {
  actionsMenuItem.value = item;
  actionsMenuRef.value?.toggle(event);
}

function onArchiveClick(item: Record<string, unknown> | null) {
  if (!item) return;

  closeMenuAndDetail();

  emit('onArchive', item);
}

function onDeleteClick(item: Record<string, unknown> | null) {
  if (!item) return;

  closeMenuAndDetail();

  emit('onDelete', item);
}

function onDetailOpen(item: Record<string, unknown>) {
  closeActionsMenu();

  selectedItem.value = item;
  detailDialogVisible.value = true;
}

function onDetailVisibleChange(visible: boolean) {
  if (!visible) selectedItem.value = null;
}

function onEditClick(item: Record<string, unknown> | null) {
  if (!item) return;

  closeMenuAndDetail();

  emit('onEdit', item);
}
</script>

<template>
  <!-- `@container`: the row answers to the width of the LIST, not of the screen. A list inside a
       tab or an accordion has to fold while a full-width list on the same screen stays a row. -->
  <div class="border-line bg-canvas @container max-w-full min-w-0 overflow-hidden rounded-lg border">
    <AEmptyState
      v-if="!items.length"
      :description="$t(emptyMessage)"
    />

    <div
      v-for="item in items"
      class="border-line flex flex-col gap-2 border-b px-4 py-3 last:border-b-0 @2xl:flex-row @2xl:items-center @2xl:justify-between @2xl:gap-4"
      :key="fieldValue(item, itemKey)"
    >
      <div class="flex min-w-0 items-start gap-3 @2xl:flex-1 @2xl:items-center">
        <Tag
          v-if="badge"
          rounded
          :class="['hidden shrink-0 @md:inline-flex', tagClass(item, badge)]"
          :value="fieldValue(item, badge.valueKey)"
        />

        <div class="flex min-w-0 flex-1 flex-col">
          <span class="text-heading text-sm font-medium break-words @2xl:truncate">
            {{ fieldValue(item, title.valueKey) }}
          </span>

          <span
            v-if="subtitle"
            class="text-muted truncate text-xs"
          >
            {{ fieldValue(item, subtitle.valueKey) }}
          </span>
        </div>

        <!-- A narrow list folds the strip into ⋮. The eye stays outside the menu: reading a row is
             not an action on it. -->
        <div
          v-if="hasRowControls(item, 'menu')"
          class="flex shrink-0 items-center gap-3 @md:hidden"
        >
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

          <Button
            v-if="hasItemActions(item, 'menu')"
            rounded
            text
            class="h-7 w-7 shrink-0 pointer-coarse:h-11 pointer-coarse:w-11"
            icon="pi pi-ellipsis-v"
            severity="secondary"
            :aria-label="$t('common.moreActions')"
            @click="(event: Event) => onActionsMenuToggle(event, item)"
          />
        </div>
      </div>

      <div class="flex min-w-0 flex-wrap items-center gap-3 @2xl:shrink-0 @2xl:justify-end">
        <div
          v-if="badge || amount || status"
          class="flex min-w-0 flex-wrap items-center gap-2"
        >
          <Tag
            v-if="badge"
            rounded
            :class="['@md:hidden', tagClass(item, badge)]"
            :value="fieldValue(item, badge.valueKey)"
          />

          <span
            v-if="amount"
            :class="['text-heading text-sm font-semibold', amount.align === 'left' ? 'text-left' : 'text-right']"
          >
            {{ amountValue(item) }}
          </span>

          <Tag
            v-if="status"
            rounded
            :class="tagClass(item, status)"
            :icon="statusIconMap?.[fieldValue(item, status.toneKey)]"
            :value="fieldValue(item, status.valueKey)"
          />
        </div>

        <!-- `gap-3`, and it is not cosmetic: this strip ends in a bin. At 4px two 28px targets sit
             closer than a fingertip is wide, and the later sibling wins the overlap — so aiming at
             the pencil lands on delete. See `06-design-system`, touch targets. -->
        <div
          v-if="hasRowControls(item, 'inline')"
          class="ml-auto hidden flex-wrap items-center justify-end gap-3 @md:flex"
        >
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
            variant="inline"
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
  </div>

  <aside class="contents">
    <Popover
      ref="actionsMenuRef"
      @hide="onActionsMenuHide"
    >
      <div
        v-if="actionsMenuItem"
        class="flex min-w-48 flex-col gap-3"
      >
        <Button
          v-if="canEdit"
          text
          class="w-full justify-start"
          icon="pi pi-pencil"
          severity="secondary"
          :label="$t('common.actions.edit')"
          @click="onEditClick(actionsMenuItem)"
        />

        <Button
          v-if="canArchive"
          text
          class="w-full justify-start"
          icon="pi pi-inbox"
          severity="secondary"
          :label="$t('common.actions.archive')"
          @click="onArchiveClick(actionsMenuItem)"
        />

        <Button
          v-if="canDelete"
          text
          class="w-full justify-start"
          icon="pi pi-trash"
          severity="danger"
          :label="$t('common.actions.delete')"
          @click="onDeleteClick(actionsMenuItem)"
        />

        <slot
          name="actions"
          variant="menu"
          :close="closeMenuAndDetail"
          :item="actionsMenuItem"
        />
      </div>
    </Popover>

    <MMainDialog
      v-model:visible="detailDialogVisible"
      title="common.details"
      :isFooterless="!selectedItem || !hasItemActions(selectedItem, 'inline')"
    >
      <slot
        v-if="selectedItem"
        name="details"
        :item="selectedItem"
      />

      <template #footer>
        <div class="flex w-full items-center justify-between gap-4">
          <div class="flex flex-wrap items-center gap-3">
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
              variant="inline"
              :close="closeDetail"
              :item="selectedItem"
            />
          </div>

          <Button
            v-if="canEdit"
            class="ml-auto"
            icon="pi pi-pencil"
            :label="$t('common.actions.edit')"
            @click="onEditClick(selectedItem)"
          />
        </div>
      </template>
    </MMainDialog>
  </aside>
</template>
