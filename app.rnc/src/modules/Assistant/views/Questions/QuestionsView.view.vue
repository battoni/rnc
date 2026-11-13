<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { AssistantQuestion, KnowledgeNote } from '../../interfaces';
import { translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { ASSISTANT_ANSWER_LIMITS, ASSISTANT_PERMISSIONS } from '../../constants';
import { formatInstantDay } from '../../helpers';
import { AnswerQuestionService, GetQuestionsService } from '../../services';

/*
 * "Perguntas para especialista" (rnc-3 R14): the questions the assistant handed over. A specialist sees
 * the ones handed to them and answers in text — the question turns respondida and the asker's bell
 * rings; `assistant.manage` reads every question of the firm, and only the assigned specialist gets
 * an answer box. "Virar nota" opens a rascunho prefilled with the question and its answer, tied to the
 * question (`from_question`), so a good answer reaches the base after another specialist's review.
 * Anyone else is refused by the API (403) and told who reads it.
 */
interface LoadError {
  response?: { status?: number };
}

interface AnswerError {
  translatedMessage?: string;
}

const STATUS_FORBIDDEN = 403;
const TOAST_LIFE_MS = 3500;

const userStore = useUserStore();

const { t } = useI18n();
const toast = useToast();

const answeringId = ref<number | null>(null);
const answerErrors = reactive<Record<number, string | null>>({});
const drafts = reactive<Record<number, string>>({});
const hasFailed = ref(false);
const isForbidden = ref(false);
const isLoading = ref(true);
const notingQuestion = ref<AssistantQuestion | null>(null);
const questions = ref<AssistantQuestion[]>([]);

const canManage = computed(() => userStore.hasPermission(ASSISTANT_PERMISSIONS.manage));
const dialogVisible = computed({
  get: () => notingQuestion.value !== null,
  set: (visible: boolean) => {
    if (!visible) notingQuestion.value = null;
  },
});

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetQuestionsService()
    .then(({ data }) => (questions.value = data))
    .catch((failure: LoadError) => {
      if (failure.response?.status === STATUS_FORBIDDEN) {
        isForbidden.value = true;
        return;
      }

      hasFailed.value = true;
    })
    .finally(() => (isLoading.value = false));
}

function isMine(question: AssistantQuestion): boolean {
  return question.specialist?.id === userStore.user?.id;
}

function replace(updated: AssistantQuestion) {
  questions.value = questions.value.map((question) => (question.id === updated.id ? updated : question));
}

// EVENTS
function onAnswer(question: AssistantQuestion) {
  const answer = (drafts[question.id] ?? '').trim();

  if (answer.length < ASSISTANT_ANSWER_LIMITS.min) {
    answerErrors[question.id] = t('assistant.tooShort');
    return;
  }

  answerErrors[question.id] = null;
  answeringId.value = question.id;

  AnswerQuestionService(question.id, answer)
    .then(({ data }) => {
      replace(data);
      toast.add({
        life: TOAST_LIFE_MS,
        severity: 'success',
        summary: t('assistant.questions.answered', { name: data.asked_by?.name ?? t('assistant.questions.someone') }),
      });
    })
    .catch((failure: AnswerError) => {
      answerErrors[question.id] = failure.translatedMessage ?? translateError('errors.unexpected');
    })
    .finally(() => (answeringId.value = null));
}

function onNoted(note: KnowledgeNote) {
  const question = notingQuestion.value;

  notingQuestion.value = null;

  if (question) replace({ ...question, note_id: note.id });

  toast.add({ life: TOAST_LIFE_MS, severity: 'success', summary: t('assistant.questions.noteCreated') });
}

function onToNote(question: AssistantQuestion) {
  notingQuestion.value = question;
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="assistant.questions.title" />
    </template>

    <div
      v-if="isLoading && questions.length === 0"
      class="flex flex-col gap-3"
    >
      <Skeleton height="1.25rem" />

      <Skeleton height="9rem" />

      <Skeleton height="9rem" />
    </div>

    <p
      v-else-if="isForbidden"
      class="bg-panel shadow-lift text-body rounded-lg p-8 text-center"
      data-testid="questions-forbidden"
      role="alert"
    >
      {{ $t('assistant.questions.forbidden') }}
    </p>

    <div
      v-else-if="hasFailed"
      class="bg-panel shadow-lift flex flex-col items-center gap-4 rounded-lg p-8 text-center"
      role="alert"
    >
      <p class="text-body">{{ $t('assistant.questions.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        :label="$t('assistant.questions.retry')"
        @click="load"
      />
    </div>

    <template v-else>
      <p class="text-body text-sm">
        {{ canManage ? $t('assistant.questions.ledeManage') : $t('assistant.questions.ledeSpecialist') }}
      </p>

      <div
        v-if="questions.length === 0"
        class="bg-panel shadow-lift rounded-lg"
        data-testid="questions-empty"
      >
        <AEmptyState
          icon="pi pi-inbox"
          :description="$t('assistant.questions.empty')"
          :title="$t('assistant.questions.emptyTitle')"
        />
      </div>

      <ul
        v-else
        class="flex min-w-0 flex-col gap-3"
      >
        <li
          v-for="(question, index) in questions"
          class="motion-enter bg-panel shadow-lift flex min-w-0 flex-col gap-3 rounded-lg p-4 sm:p-5"
          data-testid="question-row"
          :key="question.id"
          :data-id="question.id"
          :data-status="question.status"
          :style="{ '--reveal-index': index + 1 }"
        >
          <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
            <p class="text-heading min-w-0 flex-1 font-semibold break-words">{{ question.question }}</p>

            <span
              data-testid="question-status"
              :class="[
                'inline-flex shrink-0 items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-semibold',
                question.status === 'aberta' ? 'bg-warn-50 text-warn-900' : 'bg-success-50 text-success-800',
              ]"
            >
              <i
                aria-hidden="true"
                :class="[question.status === 'aberta' ? 'pi pi-clock' : 'pi pi-check', 'text-xs']"
              />
              {{ $t(`assistant.questions.status.${question.status}`) }}
            </span>
          </div>

          <span class="text-muted text-xs">
            {{
              $t('assistant.questions.meta', {
                asker: question.asked_by?.name ?? $t('assistant.questions.someone'),
                date: question.created_at ? formatInstantDay(question.created_at) : '',
                specialist: question.specialist?.name ?? $t('assistant.questions.someone'),
              })
            }}
          </span>

          <div
            v-if="question.status === 'respondida'"
            class="bg-panel-muted flex min-w-0 flex-col gap-1 rounded-md px-3 py-2.5"
            data-testid="question-answer"
          >
            <p class="text-body leading-relaxed break-words whitespace-pre-line">{{ question.answer }}</p>

            <span
              v-if="question.answered_at"
              class="text-muted text-xs"
            >
              {{ $t('assistant.questions.answeredOn', { date: formatInstantDay(question.answered_at) }) }}
            </span>
          </div>

          <form
            v-else-if="isMine(question)"
            novalidate
            class="flex min-w-0 flex-col gap-2"
            @submit.prevent="onAnswer(question)"
          >
            <label
              :class="['mb-2 block text-sm', answerErrors[question.id] ? 'text-danger-700' : 'text-muted']"
              :for="`question-answer-${question.id}`"
            >
              {{ $t('assistant.questions.answerLabel') }}
            </label>

            <Textarea
              v-model="drafts[question.id]"
              autoResize
              :id="`question-answer-${question.id}`"
              class="w-full"
              data-testid="question-answer-input"
              rows="3"
              :invalid="!!answerErrors[question.id]"
              :maxlength="ASSISTANT_ANSWER_LIMITS.max"
            />

            <span
              v-if="answerErrors[question.id]"
              class="text-danger-700 text-sm"
              data-testid="question-answer-error"
              role="alert"
            >
              {{ answerErrors[question.id] }}
            </span>

            <div class="flex justify-end">
              <Button
                data-testid="question-answer-send"
                icon="pi pi-send"
                type="submit"
                :disabled="answeringId !== null && answeringId !== question.id"
                :label="$t('assistant.questions.answerSend')"
                :loading="answeringId === question.id"
              />
            </div>
          </form>

          <span
            v-else
            class="text-muted inline-flex items-center gap-2 text-sm"
          >
            <i
              aria-hidden="true"
              class="pi pi-hourglass text-subtle text-xs"
            />
            {{
              $t('assistant.questions.waiting', {
                name: question.specialist?.name ?? $t('assistant.questions.someone'),
              })
            }}
          </span>

          <div
            v-if="question.status === 'respondida'"
            class="flex justify-end"
          >
            <span
              v-if="question.note_id !== null"
              class="bg-primary-50 text-primary-950 inline-flex items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-semibold"
              data-testid="question-noted"
            >
              <i
                aria-hidden="true"
                class="pi pi-book text-xs"
              />
              {{ $t('assistant.questions.noted') }}
            </span>

            <Button
              v-else
              data-testid="question-to-note"
              icon="pi pi-book"
              severity="secondary"
              size="small"
              :label="$t('assistant.questions.toNote')"
              @click="onToNote(question)"
            />
          </div>
        </li>
      </ul>
    </template>

    <aside class="contents">
      <MMainDialog
        v-model:visible="dialogVisible"
        isFooterless
        title="assistant.form.fromQuestionHeading"
      >
        <MAddEditNoteForm
          v-if="notingQuestion"
          :key="notingQuestion.id"
          :fromQuestion="notingQuestion.id"
          :prefill="{ answer: notingQuestion.answer ?? '', question: notingQuestion.question }"
          @onClose="dialogVisible = false"
          @onSuccess="onNoted"
        />
      </MMainDialog>
    </aside>
  </TheLayout>
</template>
