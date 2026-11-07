<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { Tip, TipHistoryEntry } from '../../../interfaces';
import { withExternalLinks } from '@Helpers';
import { useTipFormat } from '../../../composables';
import { TIP_EVENT } from '../../../enums';
import { GetTipHistoryService, RestoreTipVersionService } from '../../../services';

const emit = defineEmits<{
  onRestored: [tip: Tip];
  'update:visible': [value: boolean];
}>();

const props = defineProps<{
  canManage: boolean;
  tip: Tip | null;
  visible: boolean;
}>();

const { formatDateTime } = useTipFormat();
const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();

const entries = ref<TipHistoryEntry[]>([]);
const loading = ref(false);
const restoringId = ref<number | null>(null);

const currentContent = computed(() => props.tip?.content ?? '');

/*
 * Read newest-first. `isCurrent` marks the version whose text is live right now — which is the
 * newest CONTENT-bearing entry, not simply the newest row: archiving appends a row without
 * changing a word, and marking that one "current" would tell the reader the tip was last
 * rewritten when it was in fact only taken down.
 */
const feedEntries = computed(() =>
  entries.value.map((entry) => ({
    ...entry,
    authorName: entry.changed_by?.name ?? t('tips.history.system'),
    eventLabel: buildEventLabel(entry),
    isCurrent: entry.content === currentContent.value && entry.id === newestContentId.value,
    timestamp: formatDateTime(entry.created_at),
  }))
);
const newestContentId = computed(() => {
  const contentEvents = [TIP_EVENT.CREATED, TIP_EVENT.UPDATED, TIP_EVENT.RESTORED] as string[];

  return entries.value.find((entry) => contentEvents.includes(entry.event))?.id ?? null;
});

// `immediate` so a dialog mounted already-open loads its history instead of showing "no history".
watch(() => props.visible, onVisibleChange, { immediate: true });

// HELPERS
function buildEventLabel(entry: TipHistoryEntry): string {
  if (entry.event !== TIP_EVENT.RESTORED) return t(`tips.history.events.${entry.event}`);

  const source = entries.value.find((candidate) => candidate.id === entry.restored_from_id);

  if (!source) return t('tips.history.events.restored');

  return t('tips.history.restoredFrom', { date: formatDateTime(source.created_at) });
}

function loadHistory() {
  if (!props.tip) return;

  loading.value = true;

  GetTipHistoryService(props.tip.id)
    .then(({ data }) => (entries.value = data))
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('tips.history.loadError'),
        life: 8000,
      })
    )
    .finally(() => (loading.value = false));
}

// EVENTS
function onClose() {
  emit('update:visible', false);
}

function onRestoreConfirm(entry: TipHistoryEntry) {
  if (!props.tip) return;

  restoringId.value = entry.id;

  RestoreTipVersionService(props.tip.id, entry.id)
    .then(({ data }) => {
      emit('onRestored', data);

      loadHistory();

      toast.add({
        severity: 'success',
        summary: t('tips.history.restoredSuccess'),
        life: 5000,
      });
    })
    .catch(() =>
      toast.add({
        severity: 'error',
        summary: t('errors.somethingWentWrong'),
        life: 8000,
      })
    )
    .finally(() => (restoringId.value = null));
}

function onRestoreRequest(event: Event, entry: TipHistoryEntry) {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    group: 'tip-restore',
    message: t('tips.history.restoreConfirm'),
    acceptProps: { label: t('tips.history.restore'), size: 'small' },
    rejectProps: { label: t('common.actions.cancel'), severity: 'secondary', size: 'small', text: true },
    accept: () => onRestoreConfirm(entry),
  });
}

function onVisibleChange(isVisible: boolean) {
  if (!isVisible) {
    entries.value = [];
    return;
  }

  loadHistory();
}
</script>

<template>
  <MMainDialog
    isFooterless
    title="tips.history.title"
    :visible
    @onClose="onClose"
    @update:visible="emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-4">
      <template v-if="loading">
        <Skeleton height="6rem" />

        <Skeleton height="6rem" />
      </template>

      <p
        v-else-if="!feedEntries.length"
        class="text-muted text-sm"
      >
        {{ $t('tips.history.empty') }}
      </p>

      <article
        v-for="entry in feedEntries"
        v-else
        class="border-line-strong bg-panel flex flex-col gap-3 rounded-lg border p-4"
        data-testid="tip-history-entry"
        :key="entry.id"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-2">
            <Tag
              :severity="entry.isCurrent ? 'success' : 'secondary'"
              :value="entry.eventLabel"
            />

            <span class="text-muted text-xs">{{ entry.timestamp }}</span>
          </div>

          <span class="text-muted text-xs">{{ entry.authorName }}</span>
        </header>

        <!-- Safe: this content went through the API's write-time sanitiser, same as the tip
             itself. An older version was sanitised by the same allow-list when it was saved. -->
        <div
          class="tip-history-body text-body flex min-w-0 flex-col gap-2 text-sm break-words"
          v-html="withExternalLinks(entry.content)"
        />

        <p
          v-if="entry.change_reason"
          class="text-muted text-sm italic"
        >
          {{ entry.change_reason }}
        </p>

        <footer
          v-if="canManage && !entry.isCurrent"
          class="border-line flex justify-end border-t pt-3"
        >
          <Button
            data-testid="tip-history-restore"
            icon="pi pi-history"
            severity="secondary"
            size="small"
            :label="$t('tips.history.restore')"
            :loading="restoringId === entry.id"
            @click="onRestoreRequest($event, entry)"
          />
        </footer>
      </article>
    </div>
  </MMainDialog>

  <ConfirmPopup group="tip-restore" />
</template>

<style scoped>
@reference "../../../../../styles/main.css";

.tip-history-body :deep(p) {
  margin: 0;
}

.tip-history-body :deep(ul),
.tip-history-body :deep(ol) {
  @apply list-outside pl-5;
}

.tip-history-body :deep(ul) {
  @apply list-disc;
}

.tip-history-body :deep(ol) {
  @apply list-decimal;
}

.tip-history-body :deep(a) {
  @apply text-primary-600 underline;
}
</style>
