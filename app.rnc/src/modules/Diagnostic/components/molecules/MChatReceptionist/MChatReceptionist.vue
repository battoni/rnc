<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import type { UseChatReceptionist, UseDiagnostic } from '../../../composables';
import type { BookingConfirmation, ChatReply } from '../../../interfaces';
import { prefersReducedMotionHelper } from '@Helpers';
import { useProfileLine } from '../../../composables';
import { CHAT_SPEAKER } from '../../../enums';

/*
 * "Conversar no chat" (rnc-2 R12–R13): the reception's transcript, the turn's quick replies and a
 * box that takes typed text at every turn. The talk itself is `useChatReceptionist`, held by the view
 * so it outlives a switch to the form; this only lays it out and moves it.
 *
 * Motion (theme RULES, Motion): each line rises on mount (`motion-bubble`), the reception's question
 * lands a stagger after the visitor's answer, the replies follow one by one; while the API answers
 * the reception "types" (`motion-typing`, the one idle loop, still under reduced motion). The log
 * scrolls itself to the newest line — smoothly, unless the visitor asked for less motion.
 */
const props = defineProps<{
  chat: UseChatReceptionist;
  diagnostic: UseDiagnostic;
}>();

const emit = defineEmits<{ onSimulator: [] }>();

const { answer, errorKey, isBusy, isDone, log, prompt, send, turn, typed } = props.chat;
const { booking, company, confirmBooking, hasEmail, profile, result, step, token } = props.diagnostic;
const { profileLine } = useProfileLine(profile);

const logEl = useTemplateRef<HTMLOListElement>('logEl');
const inputEl = useTemplateRef<{ $el: HTMLInputElement }>('inputEl');
const hasAnswered = ref(false);

/** A new key per line on screen, so a new question (or an error in its place) rises in. */
const liveKey = computed(() => `${turn.value}-${errorKey.value ?? 'ask'}-${log.value.length}`);

watch(() => [log.value.length, isBusy.value, isDone.value, liveKey.value], onTranscriptChange, { flush: 'post' });

// HELPERS
function bubbleClass(speaker: CHAT_SPEAKER): string {
  return speaker === CHAT_SPEAKER.VISITOR
    ? 'bg-primary-500 text-primary-contrast self-end rounded-br-md'
    : 'bg-panel-muted text-body self-start rounded-bl-md';
}

// EVENTS
function onReply(reply: ChatReply) {
  hasAnswered.value = true;
  answer(reply);
}

function onSend() {
  hasAnswered.value = true;
  send();
}

function onBooked(confirmation: BookingConfirmation) {
  confirmBooking(confirmation);
}

function onSimulator() {
  emit('onSimulator');
}

/** The newest line in view; the cursor back in the box once the visitor is talking. */
function onTranscriptChange() {
  const behavior = prefersReducedMotionHelper() ? 'auto' : 'smooth';

  logEl.value?.scrollTo?.({ behavior, top: logEl.value.scrollHeight });

  if (!hasAnswered.value || isBusy.value) return;

  nextTick(() => inputEl.value?.$el?.focus?.({ preventScroll: true }));
}
</script>

<template>
  <section
    class="flex flex-col gap-4"
    data-testid="chat"
    :data-turn="isDone ? step : turn"
  >
    <p class="text-muted text-sm">{{ $t('diagnostic.chat.note') }}</p>

    <ol
      class="flex max-h-[min(64vh,38rem)] min-h-64 flex-col gap-3 overflow-y-auto overscroll-contain py-1 pr-1"
      data-testid="chat-log"
      ref="logEl"
      role="log"
      :aria-label="$t('diagnostic.chat.logLabel')"
    >
      <li
        v-for="line in log"
        data-testid="chat-msg"
        :key="line.id"
        :class="[
          'motion-bubble flex max-w-[85%] flex-col gap-1 rounded-xl px-4 py-3 text-sm leading-relaxed break-words',
          bubbleClass(line.speaker),
        ]"
        :data-from="line.speaker"
      >
        <span
          v-if="line.speaker === CHAT_SPEAKER.RECEPTION"
          class="text-muted text-xs font-semibold"
        >
          {{ $t('diagnostic.chat.who') }}
        </span>

        <span>{{ line.text ?? $t(line.key ?? '', line.params ?? {}) }}</span>
      </li>

      <li
        v-if="!isDone && prompt"
        data-testid="chat-live"
        :key="liveKey"
        :class="[
          'motion-bubble flex max-w-[85%] flex-col gap-1 self-start rounded-xl rounded-bl-md px-4 py-3 text-sm leading-relaxed',
          errorKey ? 'border-danger-200 bg-danger-50 text-danger-800 border' : 'bg-panel-muted text-body',
        ]"
        :role="errorKey ? 'alert' : undefined"
        :style="{ '--reveal-index': 2 }"
      >
        <span :class="['text-xs font-semibold', errorKey ? 'text-danger-800' : 'text-muted']">
          {{ $t('diagnostic.chat.who') }}
        </span>

        <span
          v-if="errorKey"
          data-testid="chat-error"
        >
          {{ $t(errorKey) }}
        </span>

        <span v-else-if="!isBusy || prompt.busy">{{ $t(prompt.ask, prompt.askParams) }}</span>

        <span
          v-if="isBusy"
          aria-hidden="true"
          class="motion-typing text-muted flex h-4 items-center gap-1"
          data-testid="chat-typing"
        >
          <span class="size-1.5 rounded-full bg-current" />
          <span class="size-1.5 rounded-full bg-current" />
          <span class="size-1.5 rounded-full bg-current" />
        </span>

        <span
          v-if="isBusy"
          class="sr-only"
        >
          {{ $t('diagnostic.chat.typing') }}
        </span>
      </li>

      <li
        v-if="isDone && result"
        class="flex flex-col"
      >
        <MChatResult
          :booking
          :company-name="company?.name ?? null"
          :needs-email="!hasEmail"
          :profile-line
          :result
          :token
          @on-booked="onBooked"
          @on-simulator="onSimulator"
        />
      </li>
    </ol>

    <template v-if="!isDone && prompt && !isBusy">
      <div
        v-if="prompt.replies.length"
        class="flex flex-wrap gap-2"
        :key="`replies-${liveKey}`"
        role="group"
        :aria-label="$t('diagnostic.chat.repliesLabel')"
      >
        <button
          v-for="(reply, index) in prompt.replies"
          class="motion-bubble touch-target border-line-strong bg-panel text-heading hover:bg-panel-muted hover:border-primary-400 focus-visible:outline-primary-400 motion-quick rounded-full border px-4 py-2 text-sm font-semibold transition-colors"
          data-testid="chat-reply"
          :key="reply.value"
          type="button"
          :data-value="reply.value"
          :style="{ '--reveal-index': index + 3 }"
          @click="onReply(reply)"
        >
          {{ reply.text ?? $t(reply.label ?? '', reply.params ?? {}) }}
        </button>
      </div>

      <form
        v-if="prompt.input"
        class="flex items-stretch gap-2"
        data-testid="chat-compose"
        @submit.prevent="onSend"
      >
        <label
          class="sr-only"
          for="chat-input"
        >
          {{ $t(prompt.input.label) }}
        </label>

        <InputText
          v-model="typed"
          id="chat-input"
          class="min-w-0 flex-1"
          data-testid="chat-input"
          ref="inputEl"
          :autocomplete="prompt.input.autocomplete"
          :inputmode="prompt.input.inputmode"
          :placeholder="$t(prompt.input.placeholder)"
        />

        <Button
          data-testid="chat-send"
          icon="pi pi-send"
          type="submit"
          :aria-label="$t('diagnostic.chat.send')"
          :disabled="!typed.trim()"
        />
      </form>
    </template>
  </section>
</template>
