<script setup lang="ts">
import { computed } from 'vue';
import type { AnswerNote } from '../../../interfaces';
import { formatInstantDay, formatPlainDay, safeUrl } from '../../../helpers';

/*
 * One answer of the assistant (rnc-3 R12, the demo's `assistBubble`): the note's question and text,
 * then where it came from — "Regra: <rule>", "Fonte: <source>, lida em <as_of>" linking the source, and
 * "revisado por <name> em <date>". The text is Franco's own note, never a sentence of the screen's.
 */
const props = defineProps<{
  note: AnswerNote;
}>();

const sourceUrl = computed(() => safeUrl(props.note.source_url));
</script>

<template>
  <article
    class="flex min-w-0 flex-col gap-3"
    data-testid="answer-card"
    :data-note="props.note.slug"
  >
    <p
      class="text-heading font-semibold break-words"
      data-testid="answer-question"
    >
      {{ props.note.question }}
    </p>

    <p
      class="text-body leading-relaxed break-words whitespace-pre-line"
      data-testid="answer-text"
    >
      {{ props.note.answer }}
    </p>

    <ul class="border-line flex min-w-0 flex-col gap-1.5 border-t pt-3 text-xs">
      <li class="flex min-w-0 items-start gap-2">
        <i
          aria-hidden="true"
          class="pi pi-book text-subtle mt-0.5 text-xs"
        />

        <span
          class="text-heading font-semibold break-words"
          data-testid="answer-rule"
        >
          {{ $t('assistant.answer.rule', { rule: props.note.rule }) }}
        </span>
      </li>

      <li class="flex min-w-0 items-start gap-2">
        <i
          aria-hidden="true"
          class="pi pi-link text-subtle mt-0.5 text-xs"
        />

        <span
          class="text-muted break-words"
          data-testid="answer-source"
        >
          <i18n-t
            keypath="assistant.answer.source"
            scope="global"
          >
            <template #label>
              <a
                v-if="sourceUrl"
                class="text-primary-700 hover:text-primary-800 font-medium underline underline-offset-4"
                rel="noopener noreferrer"
                target="_blank"
                :href="sourceUrl"
              >
                {{ props.note.source_label }}
              </a>
              <span v-else>{{ props.note.source_label }}</span>
            </template>

            <template #date>{{ formatPlainDay(props.note.as_of) }}</template>
          </i18n-t>
        </span>
      </li>

      <li class="flex min-w-0 items-start gap-2">
        <i
          aria-hidden="true"
          class="pi pi-verified text-success-700 mt-0.5 text-xs"
        />

        <span
          class="text-muted break-words"
          data-testid="answer-reviewer"
        >
          {{
            $t('assistant.answer.reviewed', {
              date: props.note.reviewed_at ? formatInstantDay(props.note.reviewed_at) : '',
              name: props.note.reviewer?.name ?? $t('assistant.answer.unknownReviewer'),
            })
          }}
        </span>
      </li>
    </ul>
  </article>
</template>
