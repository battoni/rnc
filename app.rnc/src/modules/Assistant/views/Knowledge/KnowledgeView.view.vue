<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { AssistantGap, KnowledgeNote, NoteReviewState } from '../../interfaces';
import { translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { ASSISTANT_ERROR_CODES, ASSISTANT_PERMISSIONS, NOTE_WARNING_DAYS } from '../../constants';
import { formatInstantDay } from '../../helpers';
import { GetGapsService, GetKnowledgeBaseService, RetireNoteService, ReviewNoteService } from '../../services';

/*
 * "Base da Reforma" (rnc-3 R10, R11, R15, AM4, AM19): Franco's notes, the only thing the assistant
 * answers with. The strip counts the notes live, "N notas vencem em 7 dias" (the API's count of reviews
 * 83–90 days old) and the drafts waiting for a review. Each note shows its state — rascunho, revisada,
 * revisão vencida, aposentada — who reviewed it and when, and the days left once 7 or fewer. "Revisar"
 * is offered where the API says the reader may (`can_review`: a specialist who did not write the last
 * change); "Aposentar" (for good, after a confirmation) and "Perguntas sem nota" are `assistant.manage`'s.
 * Every action replaces its one row from the API's answer.
 */
interface ActionError {
  response?: { data?: { code?: string; error?: string }; status?: number };
  translatedMessage?: string;
}

const STATUS_FORBIDDEN = 403;
const TOAST_LIFE_MS = 3500;

const STATE_CHIPS: Record<NoteReviewState, { class: string; icon: string }> = {
  aposentada: { class: 'bg-panel-muted text-muted', icon: 'pi pi-inbox' },
  rascunho: { class: 'bg-info-50 text-info-900', icon: 'pi pi-pencil' },
  revisada: { class: 'bg-success-50 text-success-800', icon: 'pi pi-verified' },
  vencida: { class: 'bg-danger-50 text-danger-800', icon: 'pi pi-exclamation-circle' },
};

const userStore = useUserStore();

const confirm = useConfirm();
const { t } = useI18n();
const toast = useToast();

const actingId = ref<number | null>(null);
const dialogVisible = ref(false);
const editingNote = ref<KnowledgeNote | null>(null);
const expiringCount = ref(0);
const gaps = ref<AssistantGap[]>([]);
const hasFailed = ref(false);
const isForbidden = ref(false);
const isLoading = ref(true);
const notes = ref<KnowledgeNote[] | null>(null);

const canManage = computed(() => userStore.hasPermission(ASSISTANT_PERMISSIONS.manage));
const dialogTitle = computed(() => (editingNote.value ? 'assistant.form.editHeading' : 'assistant.form.addHeading'));
const draftCount = computed(() => (notes.value ?? []).filter((note) => note.review_state === 'rascunho').length);
const liveCount = computed(() => (notes.value ?? []).filter((note) => note.review_state === 'revisada').length);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetKnowledgeBaseService()
    .then((base) => {
      notes.value = base.notes;
      expiringCount.value = base.expiring_count;
    })
    .catch((failure: ActionError) => {
      if (failure.response?.status === STATUS_FORBIDDEN) {
        isForbidden.value = true;
        return;
      }

      hasFailed.value = true;
    })
    .finally(() => (isLoading.value = false));

  if (canManage.value) {
    GetGapsService()
      .then(({ data }) => (gaps.value = data))
      .catch(() => (gaps.value = []));
  }
}

function isWarned(note: KnowledgeNote): boolean {
  return note.expires_in_days !== null && note.expires_in_days <= NOTE_WARNING_DAYS;
}

function replace(updated: KnowledgeNote) {
  notes.value = (notes.value ?? []).map((note) => (note.id === updated.id ? updated : note));
}

function refusalOf(failure: ActionError): string {
  const key = failure.response?.data?.error;

  return key ? translateError(key) : (failure.translatedMessage ?? translateError('errors.unexpected'));
}

/** A refusal the API names by key is a row action turned down (celer-07): warn; anything else is an error. */
function severityOf(failure: ActionError): 'error' | 'warn' {
  return failure.response?.data?.error ? 'warn' : 'error';
}

// EVENTS
function onAdd() {
  editingNote.value = null;
  dialogVisible.value = true;
}

function onEdit(note: KnowledgeNote) {
  editingNote.value = note;
  dialogVisible.value = true;
}

function onRetire(note: KnowledgeNote, event: Event) {
  confirm.require({
    accept: () => onRetireAccepted(note),
    acceptProps: { class: 'celer-button-danger', label: t('assistant.knowledge.retireYes') },
    group: 'retire-note',
    message: t('assistant.knowledge.retireConfirm'),
    rejectProps: { class: 'celer-button-secondary', label: t('assistant.knowledge.cancel') },
    target: event.currentTarget as HTMLElement,
  });
}

function onRetireAccepted(note: KnowledgeNote) {
  actingId.value = note.id;

  RetireNoteService(note.id)
    .then(({ data }) => {
      replace(data);
      toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('assistant.knowledge.retiredToast') });
    })
    .catch((failure: ActionError) =>
      toast.add({ life: TOAST_LIFE_MS, severity: severityOf(failure), summary: refusalOf(failure) })
    )
    .finally(() => (actingId.value = null));
}

function onReview(note: KnowledgeNote) {
  actingId.value = note.id;

  ReviewNoteService(note.id)
    .then(({ data }) => {
      replace(data);
      toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('assistant.knowledge.reviewedToast') });
    })
    .catch((failure: ActionError) => {
      toast.add({ life: TOAST_LIFE_MS, severity: severityOf(failure), summary: refusalOf(failure) });

      if (failure.response?.data?.code === ASSISTANT_ERROR_CODES.retired) load();
    })
    .finally(() => (actingId.value = null));
}

function onSaved(note: KnowledgeNote) {
  const wasEditing = editingNote.value !== null;

  dialogVisible.value = false;
  editingNote.value = null;
  notes.value = wasEditing
    ? (notes.value ?? []).map((row) => (row.id === note.id ? note : row))
    : [...(notes.value ?? []), note];

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('assistant.knowledge.savedToast') });
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="assistant.knowledge.title">
        <template
          v-if="notes"
          #primaryAction
        >
          <Button
            data-testid="note-add"
            icon="pi pi-plus"
            :label="$t('assistant.knowledge.add')"
            @click="onAdd"
          />
        </template>
      </ThePageHeader>
    </template>

    <div
      v-if="isLoading && !notes"
      class="flex flex-col gap-3"
    >
      <Skeleton height="2.5rem" />

      <Skeleton height="6rem" />

      <Skeleton height="22rem" />
    </div>

    <p
      v-else-if="isForbidden"
      class="bg-panel shadow-lift text-body rounded-lg p-8 text-center"
      data-testid="knowledge-forbidden"
      role="alert"
    >
      {{ $t('assistant.knowledge.forbidden') }}
    </p>

    <div
      v-else-if="hasFailed && !notes"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('assistant.knowledge.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('assistant.knowledge.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="notes">
      <p class="text-body text-sm">{{ $t('assistant.knowledge.lede') }}</p>

      <ul
        class="motion-enter grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-3"
        :aria-label="$t('assistant.knowledge.statsLabel')"
      >
        <li class="bg-panel shadow-lift flex min-w-0 items-center gap-3 rounded-lg p-4">
          <i
            aria-hidden="true"
            class="pi pi-verified text-success-700 text-lg"
          />
          <span class="text-heading text-sm font-semibold">
            {{ $t('assistant.knowledge.live', { count: liveCount }, liveCount) }}
          </span>
        </li>

        <li
          :class="[
            'shadow-lift flex min-w-0 items-center gap-3 rounded-lg p-4',
            expiringCount > 0 ? 'bg-warn-50 ring-warn-200 ring-1 ring-inset' : 'bg-panel',
          ]"
        >
          <i
            aria-hidden="true"
            :class="['pi pi-clock text-lg', expiringCount > 0 ? 'text-warn-800' : 'text-subtle']"
          />
          <span
            data-testid="knowledge-expiring"
            :class="['text-sm font-semibold', expiringCount > 0 ? 'text-warn-900' : 'text-heading']"
            :data-count="expiringCount"
          >
            {{ $t('assistant.knowledge.expiring', { count: expiringCount }, expiringCount) }}
          </span>
        </li>

        <li class="bg-panel shadow-lift flex min-w-0 items-center gap-3 rounded-lg p-4">
          <i
            aria-hidden="true"
            class="pi pi-pencil text-info-700 text-lg"
          />
          <span class="text-heading text-sm font-semibold">
            {{ $t('assistant.knowledge.drafts', { count: draftCount }, draftCount) }}
          </span>
        </li>
      </ul>

      <section class="bg-panel shadow-lift flex min-w-0 flex-col rounded-lg">
        <header class="border-line border-b p-4 sm:px-5">
          <h2 class="text-heading text-lg font-semibold">{{ $t('assistant.knowledge.listTitle') }}</h2>
        </header>

        <AEmptyState
          v-if="notes.length === 0"
          icon="pi pi-book"
          :description="$t('assistant.knowledge.empty')"
          :title="$t('assistant.knowledge.emptyTitle')"
        />

        <ul
          v-else
          class="divide-line flex min-w-0 flex-col divide-y"
        >
          <li
            v-for="(note, index) in notes"
            class="motion-enter grid min-w-0 grid-cols-1 gap-x-6 gap-y-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-5"
            data-testid="note-row"
            :key="note.id"
            :data-id="note.id"
            :data-state="note.review_state"
            :style="{ '--reveal-index': Math.min(index + 1, 8) }"
          >
            <div class="flex min-w-0 flex-col gap-1.5">
              <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span
                  data-testid="note-state"
                  :class="[
                    'inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-semibold',
                    STATE_CHIPS[note.review_state].class,
                  ]"
                >
                  <i
                    aria-hidden="true"
                    :class="[STATE_CHIPS[note.review_state].icon, 'text-xs']"
                  />
                  {{ $t(`assistant.knowledge.states.${note.review_state}`) }}
                </span>

                <span
                  v-if="isWarned(note)"
                  class="bg-warn-50 text-warn-900 inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-semibold"
                  data-testid="note-expires"
                >
                  <i
                    aria-hidden="true"
                    class="pi pi-clock text-xs"
                  />
                  {{ $t('assistant.knowledge.expiresIn', { count: note.expires_in_days }, note.expires_in_days ?? 0) }}
                </span>
              </div>

              <p
                :class="[
                  'font-semibold break-words',
                  note.review_state === 'aposentada' ? 'text-muted line-through decoration-1' : 'text-heading',
                ]"
              >
                {{ note.question }}
              </p>

              <p class="text-body line-clamp-2 text-sm break-words">{{ note.answer }}</p>

              <span class="text-muted text-xs break-words">{{ note.rule }} · {{ note.source_label }}</span>

              <span class="text-muted text-xs">
                <template v-if="note.reviewer && note.reviewed_at">
                  {{
                    $t('assistant.knowledge.reviewedBy', {
                      date: formatInstantDay(note.reviewed_at),
                      name: note.reviewer.name,
                    })
                  }}
                </template>
                <template v-else>
                  {{
                    $t('assistant.knowledge.awaiting', {
                      name: note.last_edited_by?.name ?? $t('assistant.questions.someone'),
                    })
                  }}
                </template>
              </span>
            </div>

            <div
              v-if="note.status !== 'aposentada'"
              class="flex min-w-0 flex-wrap items-start gap-3 sm:justify-end"
            >
              <Button
                v-if="note.can_review"
                data-testid="note-review"
                icon="pi pi-verified"
                size="small"
                :disabled="actingId !== null && actingId !== note.id"
                :label="$t('assistant.knowledge.review')"
                :loading="actingId === note.id"
                @click="onReview(note)"
              />

              <Button
                data-testid="note-edit"
                icon="pi pi-pencil"
                severity="secondary"
                size="small"
                :aria-label="`${$t('assistant.knowledge.edit')} — ${note.question}`"
                :label="$t('assistant.knowledge.edit')"
                @click="onEdit(note)"
              />

              <Button
                v-if="canManage"
                text
                data-testid="note-retire"
                icon="pi pi-inbox"
                severity="secondary"
                size="small"
                :aria-label="`${$t('assistant.knowledge.retire')} — ${note.question}`"
                :disabled="actingId !== null"
                :label="$t('assistant.knowledge.retire')"
                @click="onRetire(note, $event)"
              />
            </div>
          </li>
        </ul>
      </section>

      <section
        v-if="canManage"
        class="bg-panel shadow-lift flex min-w-0 flex-col rounded-lg"
        data-testid="knowledge-gaps"
      >
        <header class="border-line flex flex-col gap-1 border-b p-4 sm:px-5">
          <h2 class="text-heading text-lg font-semibold">{{ $t('assistant.knowledge.gaps.title') }}</h2>

          <p class="text-muted text-xs">{{ $t('assistant.knowledge.gaps.lede') }}</p>
        </header>

        <p
          v-if="gaps.length === 0"
          class="text-muted p-4 text-sm sm:px-5"
        >
          {{ $t('assistant.knowledge.gaps.empty') }}
        </p>

        <ol
          v-else
          class="divide-line flex min-w-0 flex-col divide-y"
        >
          <li
            v-for="gap in gaps"
            class="flex min-w-0 items-center gap-3 px-4 py-3 sm:px-5"
            data-testid="gap-row"
            :key="gap.normalised"
          >
            <strong
              class="bg-primary-50 text-primary-950 inline-flex min-w-10 shrink-0 justify-center rounded-sm px-2 py-1 text-sm tabular-nums"
              data-testid="gap-count"
            >
              {{ $t('assistant.knowledge.gaps.times', { count: gap.count }) }}
            </strong>

            <span class="text-heading min-w-0 flex-1 text-sm font-medium break-words">{{ gap.question }}</span>

            <span class="text-muted hidden shrink-0 text-xs sm:inline">
              {{ $t('assistant.knowledge.gaps.lastAsked', { date: formatInstantDay(gap.last_asked_at) }) }}
            </span>
          </li>
        </ol>
      </section>
    </template>

    <aside class="contents">
      <MConfirmPopup group="retire-note" />

      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        :title="dialogTitle"
      >
        <MAddEditNoteForm
          v-if="dialogVisible"
          :key="editingNote?.id ?? 'new'"
          :note="editingNote ?? undefined"
          @onClose="dialogVisible = false"
          @onSuccess="onSaved"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
