<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { AssistantAnswer, AssistantSuggestion } from '../../interfaces';
import { getI18nRouteName, translateError } from '@Helpers';
import { useUserStore } from '@UserModule';
import { ASSISTANT_PERMISSIONS, ASSISTANT_QUESTION_LIMITS, ASSISTANT_ROUTE_NAMES } from '../../constants';
import { AskAssistantService, GetSuggestionsService } from '../../services';

/*
 * "Assistente da Reforma" (rnc-3 R12, R13, the demo's `assistantView()`): the team asks, and the
 * answer is one of Franco's reviewed notes in date — `MAnswerCard`, with its rule, source and reviewer
 * — or, when none is served, the fixed sentence and the hand-off (`MHandoffCard`, C4). The chips are
 * the served notes' questions and ask by id. The transcript is this screen's alone: nothing of it is
 * kept, and every ask is logged by the API (R15). The way to the inbox is offered to whoever reads it
 * (a specialist, `assistant.manage`).
 */
interface Turn {
  answer?: AssistantAnswer;
  id: number;
  question: string;
}

interface AskError {
  translatedMessage?: string;
}

const TOAST_LIFE_MS = 3500;

let nextTurnId = 1;

const userStore = useUserStore();

const { t } = useI18n();
const toast = useToast();

const log = useTemplateRef<HTMLElement>('log');
const input = useTemplateRef<{ $el: HTMLInputElement }>('input');

const draft = ref('');
const inputError = ref<string | null>(null);
const isAsking = ref(false);
const isLoadingSuggestions = ref(true);
const suggestions = ref<AssistantSuggestion[]>([]);
const transcript = ref<Turn[]>([]);

const answeredCount = computed(() => transcript.value.filter((turn) => turn.answer?.note).length);
const readsInbox = computed(
  () => Boolean(userStore.user?.is_specialist) || userStore.hasPermission(ASSISTANT_PERMISSIONS.manage)
);

onMounted(onComponentMount);

// HELPERS
function ask(text: string, noteId?: number) {
  const question = text.trim();

  if (isAsking.value) return;

  if (question.length < ASSISTANT_QUESTION_LIMITS.min) {
    inputError.value = t('assistant.tooShort');
    return;
  }

  inputError.value = null;
  isAsking.value = true;

  const turn: Turn = { id: nextTurnId++, question };

  transcript.value.push(turn);
  draft.value = '';
  scrollToEnd();

  AskAssistantService(question, noteId)
    .then(({ data }) => onAnswered(turn.id, data))
    .catch((failure: AskError) => onAskFailed(turn.id, failure))
    .finally(() => {
      isAsking.value = false;
      scrollToEnd();
      input.value?.$el?.focus({ preventScroll: true });
    });
}

function scrollToEnd() {
  nextTick(() => {
    if (log.value) log.value.scrollTop = log.value.scrollHeight;
  });
}

// EVENTS
function onComponentMount() {
  GetSuggestionsService()
    .then(({ data }) => (suggestions.value = data))
    .catch(() => (suggestions.value = []))
    .finally(() => (isLoadingSuggestions.value = false));
}

function onAnswered(turnId: number, answer: AssistantAnswer) {
  transcript.value = transcript.value.map((turn) => (turn.id === turnId ? { ...turn, answer } : turn));
}

function onAskFailed(turnId: number, failure: AskError) {
  transcript.value = transcript.value.filter((turn) => turn.id !== turnId);

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'error',
    summary: failure.translatedMessage ?? translateError('assistant.askFailed'),
  });
}

function onSubmit() {
  ask(draft.value);
}

function onSuggestion(suggestion: AssistantSuggestion) {
  ask(suggestion.question, suggestion.id);
}
</script>

<template>
  <TheLayout>
    <template #pageHeader>
      <ThePageHeader title="assistant.title">
        <template
          v-if="readsInbox"
          #actions
        >
          <RouterLink
            class="celer-button-soft-primary touch-target inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold"
            data-testid="assistant-questions-link"
            :aria-label="$t('assistant.questionsLink')"
            :to="{ name: getI18nRouteName(ASSISTANT_ROUTE_NAMES.questions) }"
          >
            <i
              aria-hidden="true"
              class="pi pi-inbox"
            />

            <span class="max-md:sr-only">{{ $t('assistant.questionsLink') }}</span>
          </RouterLink>
        </template>
      </ThePageHeader>
    </template>

    <p class="text-body text-sm">{{ $t('assistant.lede') }}</p>

    <div class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:items-start">
      <section
        class="motion-enter bg-panel shadow-lift flex min-w-0 flex-col rounded-lg"
        :aria-label="$t('assistant.conversation')"
      >
        <ol
          aria-live="polite"
          class="flex max-h-[min(36rem,62vh)] min-h-[18rem] min-w-0 flex-col gap-4 overflow-y-auto p-4 sm:p-5"
          ref="log"
        >
          <li
            class="flex min-w-0 flex-col items-start gap-1"
            data-testid="assistant-greeting"
          >
            <span class="text-muted text-xs font-semibold">{{ $t('assistant.who') }}</span>

            <p class="bg-panel-muted text-body max-w-[46rem] rounded-lg rounded-tl-sm px-4 py-3 leading-relaxed">
              {{ $t('assistant.greeting') }}
            </p>
          </li>

          <template
            v-for="turn in transcript"
            :key="turn.id"
          >
            <li class="motion-enter flex min-w-0 flex-col items-end gap-1">
              <span class="text-muted text-xs font-semibold">{{ $t('assistant.you') }}</span>

              <p
                class="bg-primary-600 text-primary-50 max-w-[38rem] rounded-lg rounded-tr-sm px-4 py-2.5 break-words"
                data-testid="assistant-message-me"
              >
                {{ turn.question }}
              </p>
            </li>

            <li
              class="motion-enter flex min-w-0 flex-col items-start gap-1"
              data-testid="assistant-message-bot"
            >
              <span class="text-muted text-xs font-semibold">{{ $t('assistant.who') }}</span>

              <div
                class="bg-panel-muted ring-line w-full max-w-[46rem] rounded-lg rounded-tl-sm px-4 py-3 ring-1 ring-inset"
              >
                <div
                  v-if="!turn.answer"
                  class="flex flex-col gap-2"
                  role="status"
                >
                  <span class="text-muted text-sm">{{ $t('assistant.thinking') }}</span>

                  <Skeleton
                    height="0.75rem"
                    width="70%"
                  />
                </div>

                <MAnswerCard
                  v-else-if="turn.answer.note"
                  :note="turn.answer.note"
                />

                <MHandoffCard
                  v-else
                  :question="turn.question"
                  :specialists="turn.answer.specialists"
                />
              </div>
            </li>
          </template>
        </ol>

        <form
          novalidate
          class="border-line flex min-w-0 flex-col gap-2 border-t p-4 sm:px-5"
          @submit.prevent="onSubmit"
        >
          <div class="flex min-w-0 flex-col gap-3 sm:flex-row">
            <InputText
              v-model="draft"
              autocomplete="off"
              class="min-w-0 flex-1"
              data-testid="assistant-input"
              ref="input"
              :aria-label="$t('assistant.inputLabel')"
              :invalid="!!inputError"
              :maxlength="ASSISTANT_QUESTION_LIMITS.max"
              :placeholder="$t('assistant.inputPlaceholder')"
            />

            <Button
              data-testid="assistant-send"
              icon="pi pi-send"
              type="submit"
              :disabled="isAsking"
              :label="$t('assistant.send')"
              :loading="isAsking"
            />
          </div>

          <span
            v-if="inputError"
            class="text-danger-700 text-sm"
            data-testid="assistant-input-error"
            role="alert"
          >
            {{ inputError }}
          </span>

          <p
            v-if="answeredCount > 0"
            class="text-muted text-xs"
            data-testid="assistant-count"
          >
            {{ $t('assistant.count', { count: answeredCount }, answeredCount) }}
          </p>
        </form>
      </section>

      <section
        class="motion-enter bg-panel shadow-lift flex min-w-0 flex-col gap-3 rounded-lg p-4 sm:p-5"
        style="--reveal-index: 1"
      >
        <header class="flex flex-col gap-1">
          <h2 class="text-heading text-lg font-semibold">{{ $t('assistant.suggestionsTitle') }}</h2>

          <p class="text-muted text-xs">{{ $t('assistant.suggestionsLede') }}</p>
        </header>

        <div
          v-if="isLoadingSuggestions"
          class="flex flex-col gap-2"
        >
          <Skeleton height="2.75rem" />

          <Skeleton height="2.75rem" />

          <Skeleton height="2.75rem" />
        </div>

        <p
          v-else-if="suggestions.length === 0"
          class="text-muted text-sm"
        >
          {{ $t('assistant.suggestionsEmpty') }}
        </p>

        <ul
          v-else
          class="flex min-w-0 flex-col gap-3"
        >
          <li
            v-for="suggestion in suggestions"
            :key="suggestion.id"
          >
            <button
              class="touch-target motion-calm bg-panel-muted hover:bg-primary-50 text-heading focus-visible:ring-primary-500 flex w-full min-w-0 items-start gap-2 rounded-md px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
              data-testid="assistant-suggestion"
              type="button"
              :disabled="isAsking"
              @click="onSuggestion(suggestion)"
            >
              <i
                aria-hidden="true"
                class="pi pi-bookmark text-primary-700 mt-0.5 text-xs"
              />
              <span class="min-w-0 break-words">{{ suggestion.question }}</span>
            </button>
          </li>
        </ul>
      </section>
    </div>
  </TheLayout>
</template>
