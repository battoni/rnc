<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { KnowledgeNote, NotePayload } from '../../../interfaces';
import { translateError } from '@Helpers';
import { NOTE_FIELD_LIMITS } from '../../../constants';
import { safeUrl, todayInSaoPaulo } from '../../../helpers';
import { AddNoteService, UpdateNoteService } from '../../../services';

/*
 * A note of "Base da Reforma" written or edited (rnc-3 R10, D7): the question, Franco's answer, the
 * legal rule, the source (label and address) and the day it was read, and the strong and weak keys
 * the matcher scores. A new note is a rascunho; an edit of a reviewed one sends it back to rascunho
 * and the form says so before saving (W53). "Virar nota" (R14) opens it on a question's words and its
 * answer and sends `from_question`. The limits (`StoreNoteRequest`) are refused here first; an API
 * refusal by key — the question already a note, not in the inbox — shows its sentence. It owns the
 * call and hands the saved note to the view (celer-07).
 */
type Field =
  | 'answer'
  | 'as_of'
  | 'keywords_strong'
  | 'keywords_weak'
  | 'question'
  | 'rule'
  | 'source_label'
  | 'source_url';
type FieldErrors = Partial<Record<Field, string>>;

interface FormState {
  answer: string;
  as_of: string;
  question: string;
  rule: string;
  source_label: string;
  source_url: string;
  strong: string;
  weak: string;
}

interface SaveError {
  response?: { data?: { error?: string; errors?: Record<string, string[]> } };
  translatedMessage?: string;
}

const emit = defineEmits<{
  onClose: [];
  onSuccess: [note: KnowledgeNote];
}>();

const props = defineProps<{
  fromQuestion?: number;
  note?: KnowledgeNote;
  prefill?: { answer: string; question: string };
}>();

const KEY_SEPARATOR = ',';
const TEXT_LIMITS: Partial<Record<Field, number>> = {
  answer: NOTE_FIELD_LIMITS.answer,
  question: NOTE_FIELD_LIMITS.question,
  rule: NOTE_FIELD_LIMITS.rule,
  source_label: NOTE_FIELD_LIMITS.sourceLabel,
  source_url: NOTE_FIELD_LIMITS.sourceUrl,
};

const TODAY = todayInSaoPaulo();

const { t } = useI18n();

const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const isSaving = ref(false);

const form = reactive<FormState>({
  answer: props.note?.answer ?? props.prefill?.answer ?? '',
  as_of: props.note?.as_of ?? TODAY,
  question: props.note?.question ?? props.prefill?.question ?? '',
  rule: props.note?.rule ?? '',
  source_label: props.note?.source_label ?? '',
  source_url: props.note?.source_url ?? '',
  strong: (props.note?.keywords_strong ?? []).join(`${KEY_SEPARATOR} `),
  weak: (props.note?.keywords_weak ?? []).join(`${KEY_SEPARATOR} `),
});

const sendsBackToDraft = computed(() => props.note?.status === 'revisada');

// HELPERS
function keysOf(text: string): string[] {
  return [
    ...new Set(
      text
        .split(KEY_SEPARATOR)
        .map((key) => key.trim())
        .filter(Boolean)
    ),
  ];
}

function areKeysValid(keys: string[]): boolean {
  return (
    keys.length <= NOTE_FIELD_LIMITS.keywords &&
    keys.every((key) => key.length >= NOTE_FIELD_LIMITS.keyword.min && key.length <= NOTE_FIELD_LIMITS.keyword.max)
  );
}

function payloadOf(): NotePayload {
  return {
    answer: form.answer.trim(),
    as_of: form.as_of,
    keywords_strong: keysOf(form.strong),
    keywords_weak: keysOf(form.weak),
    question: form.question.trim(),
    rule: form.rule.trim(),
    source_label: form.source_label.trim(),
    source_url: form.source_url.trim(),
    ...(props.fromQuestion === undefined ? {} : { from_question: props.fromQuestion }),
  };
}

function validate(payload: NotePayload): FieldErrors {
  const found: FieldErrors = {};

  for (const [field, max] of Object.entries(TEXT_LIMITS) as [Field, number][]) {
    const message = textError(payload[field as keyof NotePayload] as string, max);

    if (message) found[field] = message;
  }

  if (payload.source_url && !found.source_url && !safeUrl(payload.source_url)) {
    found.source_url = t('assistant.form.url');
  }

  const dayMessage = dayError(payload.as_of);
  const strongMessage = strongKeysError(payload.keywords_strong);

  if (dayMessage) found.as_of = dayMessage;

  if (strongMessage) found.keywords_strong = strongMessage;

  if (!areKeysValid(payload.keywords_weak)) found.keywords_weak = t('assistant.form.keyword');

  return found;
}

function textError(value: string, max: number): string | null {
  if (!value) return t('assistant.form.required');

  return value.length > max ? t('assistant.form.tooLong', { max }) : null;
}

function dayError(day: string): string | null {
  if (!day) return t('assistant.form.required');

  return day > TODAY ? t('assistant.form.future') : null;
}

function strongKeysError(keys: string[]): string | null {
  if (keys.length === 0) return t('assistant.form.strongRequired');

  return areKeysValid(keys) ? null : t('assistant.form.keyword');
}

function labelClass(field: Field): string[] {
  return ['mb-2 block text-sm', errors.value[field] ? 'text-danger-700' : 'text-muted'];
}

// EVENTS
function onSubmit() {
  const payload = payloadOf();

  errors.value = validate(payload);
  formError.value = null;

  if (Object.keys(errors.value).length > 0) return;

  isSaving.value = true;

  const request = props.note ? UpdateNoteService(props.note.id, payload) : AddNoteService(payload);

  request
    .then(({ data }) => emit('onSuccess', data))
    .catch(onFailed)
    .finally(() => (isSaving.value = false));
}

function onFailed(failure: SaveError) {
  const fieldErrors = failure.response?.data?.errors ?? {};
  const fields = Object.keys(fieldErrors);

  if (fields.length === 0) {
    const key = failure.response?.data?.error;

    formError.value = key ? translateError(key) : (failure.translatedMessage ?? translateError('errors.unexpected'));
    return;
  }

  errors.value = Object.fromEntries(
    fields.map((field) => [field.split('.')[0], translateError(fieldErrors[field]?.[0] ?? 'errors.unexpected')])
  );
}
</script>

<template>
  <form
    novalidate
    id="assistant-note-form"
    class="mx-auto flex w-full max-w-[640px] flex-col gap-4"
    data-testid="note-form"
    @submit.prevent="onSubmit"
  >
    <div class="flex flex-col gap-2">
      <label
        for="note-question"
        :class="labelClass('question')"
      >
        {{ $t('assistant.form.question') }}
      </label>

      <InputText
        v-model="form.question"
        autofocus
        id="note-question"
        class="w-full"
        data-testid="note-question"
        :invalid="!!errors.question"
        :maxlength="NOTE_FIELD_LIMITS.question"
      />

      <AFormError :error="errors.question" />
    </div>

    <div class="flex flex-col gap-2">
      <label
        for="note-answer"
        :class="labelClass('answer')"
      >
        {{ $t('assistant.form.answer') }}
      </label>

      <Textarea
        v-model="form.answer"
        autoResize
        id="note-answer"
        class="w-full"
        data-testid="note-answer"
        rows="4"
        :invalid="!!errors.answer"
        :maxlength="NOTE_FIELD_LIMITS.answer"
      />

      <AFormError :error="errors.answer" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="note-rule"
          :class="labelClass('rule')"
        >
          {{ $t('assistant.form.rule') }}
        </label>

        <InputText
          v-model="form.rule"
          id="note-rule"
          class="w-full"
          data-testid="note-rule"
          :invalid="!!errors.rule"
          :maxlength="NOTE_FIELD_LIMITS.rule"
        />

        <AFormError :error="errors.rule" />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="note-as-of"
          :class="labelClass('as_of')"
        >
          {{ $t('assistant.form.asOf') }}
        </label>

        <InputText
          v-model="form.as_of"
          id="note-as-of"
          class="w-full"
          data-testid="note-as-of"
          type="date"
          :invalid="!!errors.as_of"
          :max="TODAY"
        />

        <AFormError :error="errors.as_of" />
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="note-source-label"
          :class="labelClass('source_label')"
        >
          {{ $t('assistant.form.sourceLabel') }}
        </label>

        <InputText
          v-model="form.source_label"
          id="note-source-label"
          class="w-full"
          data-testid="note-source-label"
          :invalid="!!errors.source_label"
          :maxlength="NOTE_FIELD_LIMITS.sourceLabel"
        />

        <AFormError :error="errors.source_label" />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="note-source-url"
          :class="labelClass('source_url')"
        >
          {{ $t('assistant.form.sourceUrl') }}
        </label>

        <InputText
          v-model="form.source_url"
          id="note-source-url"
          class="w-full"
          data-testid="note-source-url"
          inputmode="url"
          type="url"
          :invalid="!!errors.source_url"
          :maxlength="NOTE_FIELD_LIMITS.sourceUrl"
        />

        <AFormError :error="errors.source_url" />
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="note-strong"
          :class="labelClass('keywords_strong')"
        >
          {{ $t('assistant.form.strong') }}
        </label>

        <InputText
          v-model="form.strong"
          id="note-strong"
          class="w-full"
          data-testid="note-strong"
          :invalid="!!errors.keywords_strong"
        />

        <AFormError :error="errors.keywords_strong" />
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <label
          for="note-weak"
          :class="labelClass('keywords_weak')"
        >
          {{ $t('assistant.form.weak') }}
        </label>

        <InputText
          v-model="form.weak"
          id="note-weak"
          class="w-full"
          data-testid="note-weak"
          :invalid="!!errors.keywords_weak"
        />

        <AFormError :error="errors.keywords_weak" />
      </div>
    </div>

    <p class="text-muted text-xs leading-relaxed">{{ $t('assistant.form.keywordsHint') }}</p>

    <p
      v-if="sendsBackToDraft"
      class="bg-warn-50 text-warn-900 flex items-start gap-2 rounded-md px-3 py-2 text-sm"
      data-testid="note-back-to-draft"
    >
      <i
        aria-hidden="true"
        class="pi pi-exclamation-triangle mt-0.5 text-sm"
      />
      <span>{{ $t('assistant.form.backToDraft') }}</span>
    </p>

    <p
      v-else-if="!props.note"
      class="text-muted text-sm"
    >
      {{ $t('assistant.form.draftNote') }}
    </p>

    <AFormError :error="formError" />

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <Button
        severity="secondary"
        type="button"
        :label="$t('assistant.form.cancel')"
        @click="emit('onClose')"
      />

      <Button
        data-testid="note-save"
        type="submit"
        :label="$t('assistant.form.save')"
        :loading="isSaving"
      />
    </div>
  </form>
</template>
